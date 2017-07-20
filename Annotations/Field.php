<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.07.2017
 * Time: 18:57
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
 */

namespace Targus\ObjectFetcher\Annotations;


use Doctrine\Common\Annotations\Annotation;

/**
 * Class Type
 * @package Targus\ShopifyBundle\Objects\ObjectFetcher\Annotations
 *
 * @Annotation()
 * @Target({"PROPERTY"})
 */
class Field extends FetcherAnnotation
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var bool
     */
    public $isArray;

    /**
     * @var string
     */
    public $sourceName;

    /**
     * @var bool
     */
    public $required = false;

    /**
     * @var bool
     */
    public $nullable;

    /**
     * @var mixed
     */
    public $default;

    /**
     * @var array
     */
    public $enum;

    /**
     * @var array<string>
     */
    public $profiles;

    /**
     * @var string
     */
    public $inputDateTimeFormat;

    /**
     * @var string
     */
    public $outputDateTimeFormat;
}