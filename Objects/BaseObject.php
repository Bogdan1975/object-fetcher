<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 17.07.2017
 * Time: 15:57
 */

namespace Targus\ObjectFetcher\Objects;


class BaseObject
{

    const PROPERTY_NAMES_OBJECT = 'object';
    const PROPERTY_NAMES_DATA = 'data';

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


    public function convertToArray(string $properyNames = self::PROPERTY_NAMES_OBJECT, string $filter = self::FILTER_ALL, $profiles = [], $includeDefaultProfile = true)
    {
        if (!is_array($profiles)) {
            $profiles = (array)$profiles;
        }
        if ($includeDefaultProfile && !in_array($this->defaults['profile'], $profiles, false)) {
            $profiles[] = $this->defaults['profile'];
        }
        $data = [];
        $reflection = new \ReflectionClass(static::class);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $propName = $property->getName();
            if (in_array($propName, self::DISALLOWED_PROPERTIES, false)) {
                continue;
            }
            if (!property_exists($this, $propName)) {
                // @ToDo: Make exceprion. Targus. 17.07.2017
                throw new \Exception();
            }

            $info = isset($this->metaInfo[$propName], $this->metaInfo[$propName]['info']) ? $this->metaInfo[$propName]['info'] : null;
            if (count(array_intersect($info['profiles'], $profiles)) === 0) {
                continue;
            }
            $currentValue = $property->getValue($this);
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
        if (is_array($value)) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->convertValue($item, $properyNames, $info, $filter, $profiles, $includeDefaultProfile);
            }
        }
        if (is_object($value)) {
            if ($value instanceof BaseObject) {
                $result = $value->convertToArray($properyNames, $filter, $profiles, $includeDefaultProfile);
            } elseif ($value instanceof \DateTime) {
                $result = $value->format($info['outputDateTimeFormat']);
            } else {
                $result = (array)$value;
            }
        }

        return $result;
    }

}