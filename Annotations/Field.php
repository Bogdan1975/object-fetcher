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
 * Class Field
 * @package Targus\ShopifyBundle\Objects\ObjectFetcher\Annotations
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
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
     * @var ArraySettings
     */
    public $array;

    /**
     * @var
     */
    public $preserveKeys;

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

    /**
     * Merge with other Field annotation (inheritance)
     *
     * @param Field $obj
     */
    public function merge(Field $obj)
    {
        $this->type = $this->type ?? $obj->type;
        $this->array = $this->array ?? $obj->array;
        $this->preserveKeys = $this->preserveKeys ?? $obj->preserveKeys;
        $this->sourceName = $this->sourceName ?? $obj->sourceName;
        $this->required = $this->required ?? $obj->required;
        $this->nullable = $this->nullable ?? $obj->nullable;
        $this->default = $this->default ?? $obj->default;
        $this->enum = $this->enum ?? $obj->enum;
        $this->profiles = $this->profiles ?? $obj->profiles;
        $this->inputDateTimeFormat = $this->inputDateTimeFormat ?? $obj->inputDateTimeFormat;
        $this->outputDateTimeFormat = $this->outputDateTimeFormat ?? $obj->outputDateTimeFormat;
    }

}