<?php


namespace rabbit\httpclient;

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
     * @return Response
     */
    public function get(string $url = null, array $options = array(), string $driver = 'saber'): Response;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function head(string $url, array $options = array(), string $driver = 'saber'): Response;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function delete(string $url, array $options = array(), string $driver = 'saber'): Response;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function put(string $url, array $options = array(), string $driver = 'saber'): Response;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function patch(string $url, array $options = array(), string $driver = 'saber'): Response;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function post(string $url, array $options = array(), string $driver = 'saber'): Response;

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function options(string $url, array $options = array(), string $driver = 'saber'): Response;
}
