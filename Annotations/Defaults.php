<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 14.07.2017
 * Time: 18:57
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>. 14.07.2017
 */

namespace Targus\ObjectFetcherBundle\Annotations;


use Doctrine\Common\Annotations\Annotation;

/**
 * Class Defaults
 * @package Targus\ShopifyBundle\Objects\ObjectFetcher\Annotations
 *
 * @Annotation()
 * @Target("CLASS")
 */
class Defaults extends FetcherAnnotation
{

    /**
     * @var bool
     */
    public $required;

    /**
     * @var string
     */
    public $profile = 'common';

    /**
     * @var string
     */
    public $dateTimeFormat;
}