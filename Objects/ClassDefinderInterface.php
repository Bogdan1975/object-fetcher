<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 04.08.2017
 * Time: 11:59
 *
 * @author Bogdan Shapoval <it.targus@gmail.com>
 *
 */

namespace Targus\ObjectFetcher\Objects;


interface ClassDefinderInterface
{

    public static function getClassByData($data): string;

}