<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 17.07.2017
 * Time: 15:57
 */

namespace Targus\ObjectFetcher\Objects;


use Doctrine\ORM\PersistentCollection;

class BaseObject
{

    const PROPERTY_NAMES_OBJECT = 'object';
    const PROPERTY_NAMES_DATA = 'data';
    const PROPERTY_NAMES_SNAKE = 'snake';

    const FILTER_ALL = 'all';
    const FILTER_DIRTY_ONLY = 'dirty';

    const DISALLOWED_PROPERTIES = [
        'metaInfo',
        'defaults',
    ];

    protected $metaInfo = [];

    protected $defaults;

    /**
     * @param mixed $defaults
     */
    public function setDefaults($defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * @param string $fieldName
     * @param string $sourceName
     *
     * @return $this
     */
    public function setMap(string $fieldName, string $sourceName)
    {
        if (!array_key_exists($fieldName, $this->metaInfo)) {
            $this->metaInfo[$fieldName] = [];
        }
        $this->metaInfo[$fieldName]['map'] = $sourceName;

        return $this;
    }

    public function setInitValue(string $fieldName, $value)
    {
        if (!array_key_exists($fieldName, $this->metaInfo)) {
            $this->metaInfo[$fieldName] = [];
        }
        $this->metaInfo[$fieldName]['initValue'] = $value;

        return $this;
    }

    public function setInfo(string $fieldName, array $info)
    {
        if (!array_key_exists($fieldName, $this->metaInfo)) {
            $this->metaInfo[$fieldName] = [];
        }
        $this->metaInfo[$fieldName]['info'] = $info;

        return $this;
    }

    public function getInfo(string $fieldName)
    {

        return isset($this->metaInfo, $this->metaInfo[$fieldName], $this->metaInfo[$fieldName]['info']) ? $this->metaInfo[$fieldName]['info'] : null;
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


    public function convertToArray(string $properyNames = self::PROPERTY_NAMES_OBJECT, string $filter = self::FILTER_ALL, $profiles = [], $includeDefaultProfile = true)
    {
        if (!is_array($profiles)) {
            $profiles = (array)$profiles;
        }
        $this->collectMetaData();
        if ($includeDefaultProfile && !in_array($this->defaults['profile'], $profiles, false)) {
            $profiles[] = $this->defaults['profile'];
        }
        $data = [];
        $reflection = new \ReflectionClass(static::class);
        if ($reflection->implementsInterface(\Doctrine\ORM\Proxy\Proxy::class)) {
            $reflection = $reflection->getParentClass();
        }
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $propName = $property->getName();
            if (in_array($propName, self::DISALLOWED_PROPERTIES, false)) {
                continue;
            }

            $info = isset($this->metaInfo[$propName], $this->metaInfo[$propName]['info']) ? $this->metaInfo[$propName]['info'] : null;
            if (null === $info || count(array_intersect($info['profiles'], $profiles)) === 0) {
                continue;
            }

            $currentValue = ObjectFetcherService::getValueFromObject($this, $propName);

            $map = isset($this->metaInfo[$propName], $this->metaInfo[$propName]['map']) ? $this->metaInfo[$propName]['map'] : null;
            $needToExtract = true;
            if (self::FILTER_DIRTY_ONLY === $filter && isset($this->metaInfo[$propName]) && array_key_exists('initValue',$this->metaInfo[$propName])) {
                $oldValue = $this->metaInfo[$propName]['initValue'];
                if ($oldValue === $currentValue) {
                    $needToExtract = false;
                }
            }
            if ($needToExtract) {
                switch ($properyNames) {
                    case self::PROPERTY_NAMES_OBJECT:
                        $index = $propName;
                        break;
                    case self::PROPERTY_NAMES_DATA:
                        $index = $map ?? $propName;
                        break;
                    case self::PROPERTY_NAMES_SNAKE:
                        $index = $this->camelToSnake($propName);
                        break;
                    default:
                        // @ToDo: Make right exception. Targus. 17.07.2017
                        throw new \Exception();
                }
                $data[$index] = $this->convertValue($currentValue, $properyNames, $info, $filter, $profiles, $includeDefaultProfile);
            }
        }

        return $data;
    }

    private function convertValue($value, string $properyNames, array $info = null, string $filter, $profiles = [], $includeDefaultProfile = true) {
        $result = $value;
        $processed = false;
        if (is_array($value) || (is_object($value) && $value instanceof \IteratorAggregate)) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->convertValue($item, $properyNames, $info, $filter, $profiles, $includeDefaultProfile);
            }
            $processed = true;
        }
        if (is_object($value) && !$processed) {
            if ($value instanceof BaseObject) {
                $result = $value->convertToArray($properyNames, $filter, $profiles, $includeDefaultProfile);
            } elseif ($value instanceof \DateTime) {
                $outputDateTimeFormat = isset($info, $info['outputDateTimeFormat']) ? $info['outputDateTimeFormat'] : $this->defaults['dateTimeFormat'];
                $result = $value->format($outputDateTimeFormat);
            } else {
                $result = (array)$value;
            }
        }

        return $result;
    }

    /**
     * @return BaseObject
     */
    public static function createInstance()
    {
        return ObjectFetcherService::createObject(static::class);
    }

    /**
     * @return $this
     */
    public function collectMetaData()
    {
        ObjectFetcherService::collectMetaData($this);

        return $this;
    }

}