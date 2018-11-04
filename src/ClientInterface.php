<?php


namespace rabbit\httpclient;

/**
 * Interface ClientInterface
 * @package rabbit\httpclient
 */
interface ClientInterface
{
    /**
     * @param null $url
     * @param array $options
     * @return mixed
     */
    public function get($url = null, array $options = array());

    /**
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function head($url, array $options = array());

    /**
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function delete($url, array $options = array());

    /**
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function put($url, array $options = array());

    /**
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function patch($url, array $options = array());

    /**
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function post($url, array $options = array());

    /**
     * @param $url
     * @param array $options
     * @return mixed
     */
    public function options($url, array $options = array());
}
