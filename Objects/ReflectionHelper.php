<?php
/**
 * Created by PhpStorm.
 * User: targus
 * Date: 16.01.2018
 * Time: 17:58
 * @author Bogdan Shapoval <it.targus@gmail.com>
 */

namespace Targus\ObjectFetcher\Objects;


use Doctrine\Common\Annotations\Reader;
use Targus\ObjectFetcher\Exceptions\Exception;

class ReflectionHelper
{

    private static $reflections = [];
    private static $annotations = [];

    /**
     * @var Reader
     */
    private static $annotationReader;

    public function __construct($annotationReader)
    {
        static::$annotationReader = $annotationReader;
    }

    /**
     * @param $arg
     * @return \ReflectionClass
     * @throws \Exception
     */
    public function getClassReflection($arg): \ReflectionClass
    {
        return static::getClassReflectionStatic($arg);
    }

    /**
     * @param $arg
     * @return \ReflectionClass
     * @throws \Exception
     */
    public static function getClassReflectionStatic($arg): \ReflectionClass
    {
        $className = self::checkReflectionClassStatic($arg);
        if (!array_key_exists('self', static::$reflections[$className]) || !static::$reflections[$className]['self']) {
            static::$reflections[$className]['self'] = new \ReflectionClass($className);
        }

        return static::$reflections[$className]['self'];
    }

    /**
     * @param $arg
     * @param $propertyName
     * @return \ReflectionProperty
     * @throws \Exception
     */
    public function getPropertyReflection($arg, $propertyName): \ReflectionProperty
    {
        return static::getPropertyReflectionStatic($arg, $propertyName);
    }

    /**
     * @param $arg
     * @param $propertyName
     * @return \ReflectionProperty
     * @throws \Exception
     */
    public static function getPropertyReflectionStatic($arg, $propertyName): \ReflectionProperty
    {
        $className = self::checkReflectionClassStatic($arg);
        if (!array_key_exists($propertyName, static::$reflections[$className]['properties']) || !static::$reflections[$className]['properties'][$propertyName]) {
            static::$reflections[$className]['properties'][$propertyName] = new \ReflectionProperty($className, $propertyName);
        }

        return static::$reflections[$className]['properties'][$propertyName];
    }

    /**
     * @param $arg
     * @return \ReflectionProperty[]
     * @throws Exception
     * @throws \Exception
     */
    public function getPropertiesReflection($arg)
    {
        return static::getPropertiesReflectionStatic($arg);
    }

    /**
     * @param $arg
     * @return \ReflectionProperty[]
     * @throws Exception
     * @throws \Exception
     */
    public static function getPropertiesReflectionStatic($arg)
    {
        if (is_object($arg) && $arg instanceof \ReflectionClass) {
            $className = $arg->getName();
        } else {
            $className = self::checkReflectionClassStatic($arg);
        }
        if (!static::$reflections[$className]['hasAllProperties']) {
            $classReflection = ($arg instanceof \ReflectionClass) ? $arg : self::getClassReflectionStatic($arg);
            $properties = $classReflection->getProperties();
            foreach ($properties as $property) {
                static::$reflections[$className]['properties'][$property->getName()] = $property;
            }
            static::$reflections[$className]['hasAllProperties'] = true;
        }

        return static::$reflections[$className]['properties'];
    }

    /**
     * @param $arg
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function getParentReflection($arg)
    {
        return static::getParentReflectionStatic($arg);
    }

    /**
     * @param $arg
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public static function getParentReflectionStatic($arg)
    {
        $className = self::checkReflectionClassStatic($arg);
        if (null === static::$reflections[$className]['parent']) {
            $classReflection = ($arg instanceof \ReflectionClass) ? $arg : self::getClassReflectionStatic($arg);
            static::$reflections[$className]['parent'] = $classReflection->getParentClass();
        }

        return static::$reflections[$className]['parent'];
    }

    public function getPropertyAnnotation(\ReflectionProperty $property, $className)
    {
        return static::getPropertyAnnotationStatic($property, $className);
    }

    /**
     * @param \ReflectionProperty $property
     * @param $className
     * @return mixed
     */
    public static function getPropertyAnnotationStatic(\ReflectionProperty $property, $className)
    {
        if (!array_key_exists($className, static::$annotations)) {
            static::$annotations[$className] = [];
        }
        $propertyName = $property->getName();
        if (!array_key_exists($propertyName, static::$annotations[$className]) || !static::$annotations[$className][$propertyName]) {
            static::$annotations[$className][$propertyName] = static::$annotationReader->getPropertyAnnotation($property, $className);
        }

        return static::$annotations[$className][$propertyName];
    }

    public function getClassAnnotation(\ReflectionClass $reflection, $className)
    {
        return static::getClassAnnotationStatic($reflection, $className);
    }

    public static function getClassAnnotationStatic(\ReflectionClass $reflection, $className)
    {
        if (!array_key_exists($className, static::$annotations)) {
            static::$annotations[$className] = [];
        }
        if (!array_key_exists('self', static::$annotations[$className]) || !static::$annotations[$className]['self']) {
            static::$annotations[$className]['self'] = self::$annotationReader->getClassAnnotation($reflection, $className);
        }

        return static::$annotations[$className]['self'];
    }

    /**
     * @param $arg
     * @return string
     * @throws Exception
     */
    public function checkReflectionClass($arg)
    {
        return static::checkReflectionClassStatic($arg);
    }

    /**
     * @param $arg
     * @return string
     * @throws Exception
     */
    public static function checkReflectionClassStatic($arg)
    {
        if (is_string($arg)) {
            $className = $arg;
        } elseif (is_object($arg)) {
            $className = ($arg instanceof \ReflectionClass) ? $arg->getName() : get_class($arg);
        } else {
            throw new Exception('Undefined type of argument. Should be string or object');
        }
        if (!array_key_exists($className, static::$reflections)) {
            static::$reflections[$className] = [];
            static::$reflections[$className]['parent'] = null;
            static::$reflections[$className]['properties'] = [];
            static::$reflections[$className]['hasAllProperties'] = false;
        }

        return $className;
    }

}