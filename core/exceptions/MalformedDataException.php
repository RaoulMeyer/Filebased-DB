<?php
namespace exceptions;

/**
 * Created by PhpStorm.
 * User: Raoul
 * Date: 22/1/2016
 * Time: 20:15
 */
class MalformedDataException extends \Exception {

    public function __construct($message, $code = 0, \Exception $previous = null) {

        parent::__construct($message, $code, $previous);
    }

}