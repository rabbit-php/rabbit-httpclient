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
    /** @var string */
    private $default;

    /**
     * Client constructor.
     * @param array $options
     * @param string $default
     * @param array $driver
     */
    public function __construct(array $options = array(), string $default = 'saber', array $driver = [])
    {
        if (empty($driver)) {
            $this->driver['saber'] = Saber::create($options);
            $this->driver['guzzle'] = new \GuzzleHttp\Client($options);
        } else {
            $this->driver = $driver;
        }
        $this->default = $default;
    }

    /**
     * @param string|null $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function get(string $url = null, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('GET', $url, $options, $driver);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function head(string $url, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('HEAD', $url, $options, $driver);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function delete(string $url, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('DELETE', $url, $options, $driver);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function put(string $url, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('PUT', $url, $options, $driver);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function patch(string $url, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('PATCH', $url, $options, $driver);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function post(string $url, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('POST', $url, $options, $driver);
    }

    /**
     * @param string $url
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function options(string $url, array $options = array(), string $driver = null): Response
    {
        return $this->doRequest('OPTIONS', $url, $options, $driver);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $options
     * @param string $driver
     * @return Response
     * @throws \Exception
     */
    protected function doRequest(string $method, string $url, array $options, string $driver = null): Response
    {
        try {
            if ($driver === 'saber' || ($driver === null && $this->default === 'saber')) {
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
