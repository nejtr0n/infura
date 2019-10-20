<?php

namespace App\Exceptions;

use RuntimeException;

class TimeoutExceed extends RuntimeException
{
    protected $message = "timeout exceed";
}