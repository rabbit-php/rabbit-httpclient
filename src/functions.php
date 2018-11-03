<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/3
 * Time: 20:17
 */

use GuzzleHttp\Client;
use GuzzleHttp\DefaultHandler;

if (!function_exists('guzzleHandler')) {
    function guzzleHandler(string $handler = SwooleHandler::class)
    {
        DefaultHandler::setDefaultHandler($handler);
    }
}