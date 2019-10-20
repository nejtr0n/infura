<?php


namespace App\Infura;

use Ratchet\RFC6455\Messaging\Message;

/**
 * Callback для обработки сообщений
 * Interface MessageHandler
 * @package App\Infura
 */
interface MessageHandler
{
    public function handle(array $msg);
}