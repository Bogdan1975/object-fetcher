<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.07.2017
 * Time: 18:42
 */

namespace Targus\ObjectFetcher\Objects;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Targus\ObjectFetcher\Annotations\ArraySettings;
use Targus\ObjectFetcher\Annotations\Defaults;
use Targus\ObjectFetcher\Annotations\Field;
use Targus\ObjectFetcher\Exceptions\Exception;
use Targus\ObjectFetcher\Exceptions\MissingMandatoryField;
use Targus\ObjectFetcher\Exceptions\TypeConversionException;
use Targus\ObjectFetcher\Exceptions\ValidationError;


class ObjectFetcherService
{

    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_ARRAY = 'array';
    const TYPE_OBJECT = 'object';
    const TYPE_DATE = 'date';
    const TYPE_RAW = 'raw';

    const TYPES = [
        self::TYPE_STRING,
        self::TYPE_INTEGER,
        self::TYPE_FLOAT,
        self::TYPE_BOOLEAN,
        self::TYPE_ARRAY,
        self::TYPE_OBJECT,
        self::TYPE_DATE,
        self::TYPE_RAW,
    ];

    const BUILTIN_TYPES = [
        'string' => self::TYPE_STRING,
        'int' => self::TYPE_INTEGER,
        'float' => self::TYPE_FLOAT,
        'bool' => self::TYPE_BOOLEAN,
        'array' => self::TYPE_ARRAY,
        'object' => self::TYPE_OBJECT,
    ];

    /**
     * @var Reader
     */
    private $annotationReader;

    /**
     * @var PropertyInfoExtractor
     */
    private $propertyInfoExtractor;

    /**
     * @var array
     */
    private $defaults;

    public function __construct(Reader $annotationReader, $config)
    {
        $this->annotationReader = $annotationReader;

        // a full list of extractors is shown further below
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        // array of PropertyListExtractorInterface
        $listExtractors = array($reflectionExtractor);

        // array of PropertyTypeExtractorInterface
        $typeExtractors = array($phpDocExtractor, $reflectionExtractor);

        // array of PropertyDescriptionExtractorInterface
        $descriptionExtractors = array($phpDocExtractor);

        // array of PropertyAccessExtractorInterface
        $accessExtractors = array($reflectionExtractor);

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors
        );
        $this->propertyInfoExtractor = $propertyInfo;

        $this->defaults = $config['defaults'];
    }

    public function fetch(string $className, array $data, $profiles = [], $includeDefaultProfile = true)
    {
        if (!is_array($profiles)) {
            $profiles = (array)$profiles;
        }
        if ($includeDefaultProfile && !in_array($this->defaults['profile'], $profiles, false)) {
            $profiles[] = $this->defaults['profile'];
        }
        $reflection = new \ReflectionClass($className);
        $properties = $reflection->getProperties();
        $obj = new $className();

        /** @var Defaults $classDefaults */
        $classDefaults = $this->annotationReader->getClassAnnotation($reflection, Defaults::class);
        $defaults = [
            'required' => $classDefaults && null !== $classDefaults->required ? $classDefaults->required : $this->defaults['required'],
            'profile' => $classDefaults && null !== $classDefaults->profile ? $classDefaults->profile : $this->defaults['profile'],
            'dateTimeFormat' => $classDefaults && null !== $classDefaults->dateTimeFormat ? $classDefaults->dateTimeFormat : $this->defaults['dateTimeFormat'],
            'nullable' => $classDefaults && null !== $classDefaults->nullable ? $classDefaults->nullable : $this->defaults['nullable'],
        ];
        if ($obj instanceof BaseObject) {
            $obj->setDefaults($defaults);
        }

        foreach ($properties as $property) {
            /** @var Field|null $fieldInfoAnnot */
            $fieldInfoAnnot = $this->annotationReader->getPropertyAnnotation($property, Field::class);
            $info = [];
            if ($fieldInfoAnnot) {
                $types = $this->propertyInfoExtractor->getTypes($className, $property->getName());
                $info = $this->getInfoByFieldAnnot($fieldInfoAnnot, $defaults, $types);
            }

            if (!empty($info)) {
                if ($obj instanceof BaseObject) {
                    $obj->setInfo($property->getName(), $info);
                }
                if (count(array_intersect($info['profiles'], $profiles))) {
                    $this->hydrateProperty($obj, $data, $info, $property, $profiles, $includeDefaultProfile);
                }
            }
        }

        // Custom obj validation
        if (method_exists($obj, 'validate') && !$obj->validate()) {
            // @ToDo: Make exception. Targus. 14.07.2017
        }

        return $obj;
    }

    private function getInfoByFieldAnnot(Field $annot, $defaults, array $types = null)
    {
        $types = (array)$types;
        $info = [
            'required' => $defaults['required'],
            'isArray' => false,
        ];

        /** @var \Symfony\Component\PropertyInfo\Type $type */
        $type = (count($types) === 0 && count($types) > 1) ? null : $type = array_pop($types);

        // Define 'type' and 'isArray' attributes
        if ($annot->type) {
            $info['type'] = $annot->type;
            if (null !== $annot->array) {
                $info['isArray'] = $annot->array;
            }
        } else {
            if (null === $type) {
                $info['type'] = self::TYPE_RAW;
            } else {
                $typeSource = $type->isCollection() ? $type->getCollectionValueType() : $type;
                $info['isArray'] = $annot->array ?? $type->isCollection();
                $info['type'] = $typeSource ? self::BUILTIN_TYPES[$typeSource->getBuiltinType()] : self::TYPE_RAW;
                if ('object' === $info['type']) {
                    $info['type'] = $typeSource->getClassName();
                    if ($info['type'] === 'DateTime') {
                        $info['type'] = 'date';
                    }
                }
            }
        }

        // Convert 'isArray' to a single 'ArraySettings' annotation object
        if (is_array($info['isArray'])) {
            $info['isArray'] = array_pop($info['isArray']);
        }
        if (is_bool($info['isArray']) && $info['isArray']) {
            $info['isArray'] = new ArraySettings([]);
        }

        // Define 'nullable' attribute
        $info['nullable'] = $annot->nullable ?? ( $type ? $type->isNullable() : $defaults['nullable'] );

        // Define 'required' attribute
        if (null !== $annot->required) {
            $info['required'] = $annot->required;
        }

        // Define 'sourceName' attribute
        if (null !== $annot->sourceName) {
            $info['sourceName'] = $annot->sourceName;
        }

        // Define 'enum' attribute
        if (null !== $annot->enum) {
            $info['enum'] = $annot->enum;
        }

        // Define 'profiles' attribute
        $info['profiles'] = empty($annot->profiles) ? [$defaults['profile']] : $annot->profiles;

        // Define 'default' attribute
        $info['default'] = $annot->default;

        $info['inputDateTimeFormat'] = $annot->inputDateTimeFormat ?? $defaults['dateTimeFormat'];
        $info['outputDateTimeFormat'] = $annot->outputDateTimeFormat ?? $defaults['dateTimeFormat'];

        return $info;
    }

    /**
     * @param $obj
     * @param $data
     * @param $info
     * @param \ReflectionProperty $property
     *
     * @throws Exception
     *
     * @throws MissingMandatoryField
     * @throws ValidationError
     */
    private function hydrateProperty($obj, $data, $info, \ReflectionProperty $property, $profiles, $includeDefaultProfile)
    {
        $propName = $property->getName();
        $dataPropName =  $info['sourceName'] ?? $propName;
        try {
            $tmp = $this->getValueFromData($data, $dataPropName);
            $value = $tmp['value'];
            $mappedFrom = $tmp['mappedFrom'];
        } catch (MissingMandatoryField $e) {
            if ($info['required']) {
                throw new MissingMandatoryField("Field '$propName' is mandatory");
            }
            $value = $info['default'] ?? $property->getValue($obj);
            $mappedFrom = null;
        }
        if (null === $value && !$info['nullable']) {
            $className = get_class($obj);
            throw new MissingMandatoryField("Field '$propName' is not nullable. Class '$className'");
        }

        if (null !== $value) {
            $value = $this->fetchToType($value, $info, $profiles, $includeDefaultProfile);
        }

        // Validate enum values
        if (null != $value && !empty($info['enum']) && !in_array($value, $info['enum'], true)) {
            $enumValues = "'" . implode("', '", $info['enum']) . "'";
            throw new ValidationError("Value '{$value}' is out of enum range. Only $enumValues values allowed");
        }

        // Validate by rules
        if (!$this->validate($info, $value)) {
            // @ToDo: Make exception. Targus. 14.07.2017
        }

        // Custom field validation
        if (method_exists($obj, 'validateField') && !$obj->validateField($property->getName(), $value)) {
            // @ToDo: Make exception. Targus. 14.07.2017
        }

        $modifiers = $property->getModifiers();
        if ($modifiers & (\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED)) {
            if (in_array($property->getName(), ['name', 'class'], false)) {
                throw new Exception("Impossible to make accessible private property with name 'class' or 'name'");
            }
            $property->setAccessible(true);
        }
        if ($modifiers & \ReflectionProperty::IS_STATIC) {
            $property->setValue($value);
        } else {
            $property->setValue($obj, $value);
        }
        if ($modifiers & (\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED)) {
            $property->setAccessible(false);
        }
        if ($obj instanceof BaseObject) {
            if (null !== $mappedFrom) {
                $obj->setMap($propName, $mappedFrom);
            }
            $obj->setInitValue($propName, $value);
        }

    }

    private function snakeToCamel($str)
    {
        $segments = explode('_', $str);
        $newSegments = [];
        foreach ($segments as $idx => $segment) {
            if (0 !== $idx) {
                $newSegments[] = ucfirst($segment);
            }
        }

        return implode('', $newSegments);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private function camelToSnake(string $str)
    {
        if (!is_string($str)) {
            return $str;
        }
        $newStr = '';
        $strLen = strlen($str);
        for ($i = 0; $i < $strLen; $i++) {
            $lowCase = strtolower($str[$i]);
            if ($str[$i] != $lowCase) {
                $newStr .= '_' . $lowCase;
            } else {
                $newStr .= $str[$i];
            }
        }

        return $newStr;
    }

    /**
     * @param mixed $data
     * @param string $name
     *
     * @return mixed
     *
     * @throws MissingMandatoryField
     */
    private function getValueFromData($data, string $name)
    {
        $mappedFrom = $name;
        if (!array_key_exists($name, $data)) {
            $mappedFrom = $this->camelToSnake($name);
            if (!array_key_exists($mappedFrom, $data)) {
                throw new MissingMandatoryField('');
            }
        }

        return [
            'mappedFrom' => $mappedFrom,
            'value' => $data[$mappedFrom],
        ];
    }

    private function fetchToTypeSimple($value, $info, $profiles, $includeDefaultProfile)
    {
        $type = $info['type'];
        if (!in_array($type, self::TYPES, false) && !class_exists($type)) {
            throw new TypeConversionException("Unknown type '$type'");
        }
        $newValue = null;

        if (null === $value && $info['nullable']) {
            return null;
        }

        switch ($type) {
            case self::TYPE_STRING:
                $newValue = (string)$value;
                break;
            case self::TYPE_INTEGER:
                $newValue = (int)$value;
                break;
            case self::TYPE_FLOAT:
                $newValue = (float)$value;
                break;
            case self::TYPE_BOOLEAN:
                $newValue = (bool)$value;
                break;
            case self::TYPE_DATE:
                try {
                    $newValue = \DateTime::createFromFormat($info['inputDateTimeFormat'], $value);
                } catch (\Exception $e) {
                    throw new TypeConversionException("Can't parse '$value' datetime value to '{$info['inputDateTimeFormat']}' format");
                }
                break;
            case self::TYPE_RAW:
                $newValue = $value;
                break;
            case self::TYPE_ARRAY:
                $newValue = $this->fetch($type, $value, $profiles, $includeDefaultProfile);
                break;
            default:
                if (!class_exists($type)) {
                    throw new TypeConversionException("Unknown type '$type'");
                }
                $newValue = $value;
                break;
        }

        return $newValue;
    }

    private function fetchToType($value, $info, $profiles, $includeDefaultProfile)
    {
        $isArray = $info['isArray'];
        if ($isArray) {
            $newValue = [];
            foreach ($value as $key => $item) {
                if ($isArray->preserveKeys && ($isArray->preserveOnlyStringKeys === false || !is_numeric($key))) {
                    $newValue[$key] = $this->fetchToTypeSimple($item, $info, $profiles, $includeDefaultProfile);
                } else {
                    $newValue[] = $this->fetchToTypeSimple($item, $info, $profiles, $includeDefaultProfile);
                }
            }
        } else {
            $newValue = $this->fetchToTypeSimple($value, $info, $profiles, $includeDefaultProfile);
        }

        return $newValue;
    }

    private function validate($info, $value)
    {
        return true;
    }

}