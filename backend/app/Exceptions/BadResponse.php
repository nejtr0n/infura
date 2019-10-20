<?php


namespace App\Exceptions;

use RuntimeException;

class BadResponse extends RuntimeException
{
    protected $message = "bad response";
}