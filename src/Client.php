<?php

namespace rabbit\httpclient;

use rabbit\App;
use Swlib\Saber;

/**
 * Class Client
 * @package rabbit\consul
 */
class Client implements ClientInterface
{
    /** @var array */
    protected $driver = [];

    /**
     * Client constructor.
     * @param array $options
     * @param array $driver
     */
    public function __construct(array $options = array(), array $driver = [])
    {
        if (empty($driver)) {
            $this->driver['saber'] = Saber::create($options);
            $this->driver['guzzle'] = new \GuzzleHttp\Client($options);
        } else {
            $this->driver = $driver;
        }
    }

    /**
     * @param string|null $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function get(string $url = null, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('GET', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function head(string $url, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('HEAD', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function delete(string $url, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('DELETE', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function put(string $url, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('PUT', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function patch(string $url, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('PATCH', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function post(string $url, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('POST', $url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     */
    public function options(string $url, array $options = array(), string $driver = 'saber'): Response
    {
        return $this->doRequest('OPTIONS', $url, $options);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     * @throws \Exception
     */
    protected function doRequest(string $method, string $url, array $options, string $driver = 'saber'): Response
    {
        try {
            if ($driver === 'saber') {
                $options = array_merge($options, [
                    'method' => $method,
                    'uri' => $url
                ]);
                $response = $this->getDriver($driver)->request($options);
            } else {
                $response = $this->getDriver($driver)->request($method, $url, $options);
            }
        } catch (\Exception $e) {
            $message = sprintf('Something went wrong (%s).', $e->getMessage());

            App::error($message, 'http');

            throw new \RuntimeException($message);
        }

        if (400 <= $response->getStatusCode()) {
            $message = sprintf('Something went wrong (%s - %s).', $response->getStatusCode(),
                $response->getReasonPhrase());

            App::error($message, 'http');

            $message .= "\n" . (string)$response->getBody();
            if (500 <= $response->getStatusCode()) {
                throw new \RuntimeException($message, $response->getStatusCode());
            }

            throw new \RuntimeException($message, $response->getStatusCode());
        }

        return new Response($response);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getDriver(string $name)
    {
        return $this->driver[$name];
    }
}
