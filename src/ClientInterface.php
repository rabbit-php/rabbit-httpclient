<?php


namespace rabbit\httpclient;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface ClientInterface
 * @package rabbit\httpclient
 */
interface ClientInterface
{
    /**
     * @param string|null $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function get(string $url = null, array $options = array(), string $driver = 'saber'): ResponseInterface;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function head(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function delete(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function put(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function patch(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function post(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return ResponseInterface
     */
    public function options(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface;
}
