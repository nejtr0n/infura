<?php

namespace App\Services;

interface SenderContract
{
    public function send(array $data);
}