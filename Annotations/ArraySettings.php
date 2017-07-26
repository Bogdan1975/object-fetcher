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
 * Class ArraySettings
 * @package Targus\ShopifyBundle\Objects\ObjectFetcher\Annotations
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
 *
 * @Annotation()
 * @Target({"PROPERTY","ANNOTATION"})
 */
class ArraySettings extends FetcherAnnotation
{

    /**
     * @var bool
     */
    public $preserveKeys = true;

    /**
     * @var bool
     */
    public $preserveOnlyStringKeys = true;

}