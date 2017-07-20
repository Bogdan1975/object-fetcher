<?php
/**
 * Created by PhpStorm.
 * User: Targus
 * Date: 19.07.2017
 * Time: 18:43
 */

namespace Targus\ObjectFetcherBundle\Exceptions;


class TypeConversionException extends Exception
{
    private $type;

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

}