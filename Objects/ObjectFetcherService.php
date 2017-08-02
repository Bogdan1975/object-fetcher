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

    private static $TYPESCRIPT_TYPES;

    /**
     * @var Reader
     */
    private static $annotationReader;

    /**
     * @var PropertyInfoExtractor
     */
    private static $propertyInfoExtractor;

    /**
     * @var array
     */
    private static $defaults;

    public function __construct(Reader $annotationReader, $config)
    {
        self::$annotationReader = $annotationReader;

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
        self::$propertyInfoExtractor = $propertyInfo;

        self::$defaults = $config['defaults'];

        self::$TYPESCRIPT_TYPES = [
                self::TYPE_STRING => 'string',
                self::TYPE_INTEGER => 'numeric',
                self::TYPE_FLOAT => 'numeric',
                self::TYPE_BOOLEAN => 'boolean',
                self::TYPE_OBJECT => 'object',
                self::TYPE_DATE => 'Date',
                self::TYPE_RAW => 'any',
        ];
    }

    public static function createObject(string $className)
    {
        $reflection = new \ReflectionClass($className);
        $properties = $reflection->getProperties();
        $obj = new $className();

        /** @var Defaults $classDefaults */
        $classDefaults = self::$annotationReader->getClassAnnotation($reflection, Defaults::class);
        $defaults = [
            'required' => $classDefaults && null !== $classDefaults->required ? $classDefaults->required : self::$defaults['required'],
            'profile' => $classDefaults && null !== $classDefaults->profile ? $classDefaults->profile : self::$defaults['profile'],
            'dateTimeFormat' => $classDefaults && null !== $classDefaults->dateTimeFormat ? $classDefaults->dateTimeFormat : self::$defaults['dateTimeFormat'],
            'nullable' => $classDefaults && null !== $classDefaults->nullable ? $classDefaults->nullable : self::$defaults['nullable'],
        ];
        if ($obj instanceof BaseObject) {
            $obj->setDefaults($defaults);
        }

        foreach ($properties as $property) {
            /** @var Field|null $fieldInfoAnnot */
            $fieldInfoAnnot = self::$annotationReader->getPropertyAnnotation($property, Field::class);
            $info = [];
            if ($fieldInfoAnnot) {
                $types = self::$propertyInfoExtractor->getTypes($className, $property->getName());
                $info = self::getInfoByFieldAnnot($fieldInfoAnnot, $defaults, $types);
            }

            if (!empty($info)) {
                if ($obj instanceof BaseObject) {
                    $obj->setInfo($property->getName(), $info);
                }
            }
        }

        return $obj;
    }

    public function fetch(string $className, array $data, $profiles = [], $includeDefaultProfile = true)
    {
        if (!is_array($profiles)) {
            $profiles = (array)$profiles;
        }
        if ($includeDefaultProfile && !in_array(self::$defaults['profile'], $profiles, false)) {
            $profiles[] = self::$defaults['profile'];
        }

        /** @var BaseObject $obj */
        $obj = self::createObject($className);

        $reflection = new \ReflectionClass($className);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $info = $obj->getInfo($property->getName());
            if (!empty($info)) {
                if (count(array_intersect($info['profiles'], $profiles))) {
                    $this->hydrateProperty($obj, $data, $info, $property, $profiles, $includeDefaultProfile);
                }
            }
        }

        // Custom obj validation
        if (method_exists($obj, 'validate') && !$obj->validate()) {
            // @ToDo: Make custom exception. Targus. 14.07.2017
            throw new \Exception('Validation error');
        }

        return $obj;
    }

    private static function getInfoByFieldAnnot(Field $annot, $defaults, array $types = null)
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
     * @throws MissingMandatoryField
     * @throws ValidationError
     */
    private function hydrateProperty($obj, $data, $info, \ReflectionProperty $property, $profiles, $includeDefaultProfile)
    {
        $propName = $property->getName();
        $className = get_class($obj);
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
            throw new MissingMandatoryField("Field '$propName' is not nullable. Class '$className'");
        }

        if (null !== $value) {
            try {
                $value = $this->fetchToType($value, $info, $profiles, $includeDefaultProfile);
            } catch (\Exception $e) {
                throw new Exception("Object '{$className}'. '{$propName}' field fetching error. \n" . $e->getMessage());
            }
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
            default:
                if (!class_exists($type)) {
                    throw new TypeConversionException("Unknown type '$type'");
                }
                $newValue = $this->fetch($type, $value, $profiles, $includeDefaultProfile);
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

    public function createInterface(string $className, array $created = [])
    {
        $tmp = explode('\\', $className);
        $className_ = array_pop($tmp);
        $interfaceName = 'I' . $className_;
        $created[] = $interfaceName;

        $fetchText = 'export function fetchDataTo' . $interfaceName . "(data): $interfaceName {" . PHP_EOL;
        $fetchText .= '    let obj = {};' . PHP_EOL;
        $classText = "export class $className_ implements $interfaceName {" . PHP_EOL;
        $text = 'export interface ' . $interfaceName . ' {' . PHP_EOL;

        /** @var BaseObject $obj */
        $obj = self::createObject($className);
        $depenndencies = '';

        $reflection = new \ReflectionClass($className);
        $properties = $reflection->getProperties();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $info = $obj->getInfo($propertyName);
            if (!empty($info)) {
                $text .= '    ' . $propertyName;
                $classText .= '    public ' . $propertyName . ': ';
                if (!$info['required']) {
                    $text .= '?';
                } else {
                    $fetchText .= "    if (!data.hasOwnProperty('{$propertyName}')) throw new Error('Property \"{$propertyName}\" is required');" . PHP_EOL;
                }
                if (!$info['nullable']) {
                    $fetchText .= "    if (!data.hasOwnProperty('{$propertyName}')) throw new Error('Property \"{$propertyName}\" is required');" . PHP_EOL;
                }
                $text .= ': ';
                $newType = self::convertTypeToTypescript($info['type']);
                $itemClassName = $newType;
                if ($newType === 'object') {
                    $tmp = explode('\\', $info['type']);
                    $itemClassName = array_pop($tmp);
                    $itemInterfaceName = 'I' . $itemClassName;
                    if (!in_array($itemInterfaceName, $created)) {
                        $dep = $this->createInterface($info['type'], $created);
                        $depenndencies .= $dep['text'] . PHP_EOL . PHP_EOL;
                        $created = array_merge($created, $dep['created']);
                    }
                    $newType = $itemInterfaceName;
                    $fetchVal = 'fetchDataTo' . $itemInterfaceName . "(%s)";
                } else {
                    $fetchVal = "data.{$propertyName}";
                }
                $text .= $newType;
                $classText .= $itemClassName;
                if ($info['isArray']) {
                    $text .= '[]';
                    $classText .= '[]';
                    $fetchText .= "    if (typeof(data.{$propertyName}) !== 'object && !Array.isArray(data.{$propertyName})) throw new Error('Property \"$propertyName\" must be an array');" . PHP_EOL;
                    $fetchVal = "data.{$propertyName}.map(item => " . sprintf($fetchVal, 'item') . ')';
                } else {
                    $fetchVal = sprintf($fetchVal, "data.{$propertyName}");
                }
                $classText .= ';' . PHP_EOL;
                $indent = '';
                if (!$info['required']) {
                    $fetchText .= "    if (data.hasOwnProperty('{$propertyName}')) {" . PHP_EOL;
                    $indent = '    ';
                }
                if (!$info['nullable']) {
                    $fetchText .= $indent . "    if (data.$propertyName == null) throw new Error('Property \"$propertyName\" is not nullable, null given as value');" . PHP_EOL;
                    $fetchText .= $indent . "    obj.{$propertyName} = {$fetchVal};" . PHP_EOL;
                } else {
                    if ($fetchVal === "data.{$propertyName}") {
                        $fetchText .= $indent . "    obj.{$propertyName} = data.{$propertyName};" . PHP_EOL;
                    } else {
                        $fetchText .= $indent . "    obj.{$propertyName} = data.{$propertyName} ? {$fetchVal} : null;" . PHP_EOL;
                    }
                }
                if (!$info['required']) {
                    $fetchText .= '    }' . PHP_EOL;
                }
                $text .= ';' . PHP_EOL;
            }
        }
        $text .= '}';

        $fetchText .= PHP_EOL . '    return obj;' . PHP_EOL;
        $fetchText .= '}' . PHP_EOL;

        $classText .= PHP_EOL . '    constructor(data: any) {' . PHP_EOL;
        $classText .= '        Object.assign(this, fetchDataTo' . $interfaceName . '(data));' . PHP_EOL;
        $classText .= '    }' . PHP_EOL;
        $classText .= '}' . PHP_EOL;

        $result = [
            'text' => $depenndencies . $text . PHP_EOL . PHP_EOL . $fetchText . PHP_EOL . $classText,
            'created' => $created,
        ];

        return $result;
    }

    private static function convertTypeToTypescript(string $type)
    {
        if (!in_array($type, self::TYPES, false) && !class_exists($type)) {
            throw new TypeConversionException("Unknown type '$type'");
        }
        if (!in_array($type, self::TYPES, false) && class_exists($type)) {
            $newType = 'object';
        } else {
            $newType = self::$TYPESCRIPT_TYPES[$type];
        }

        return $newType;
    }

}