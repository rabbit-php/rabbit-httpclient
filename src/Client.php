<?php

namespace rabbit\httpclient;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use rabbit\App;
use rabbit\helper\JsonHelper;
use Swlib\Saber;

/**
 * Class Client
 * @package rabbit\consul
 */
class Client implements ClientInterface
{
    /** @var array */
    protected $driver = [];
    /** @var LoggerInterface */
    protected $logger;

    /**
     * Client constructor.
     * @param array $options
     * @param LoggerInterface|null $logger
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
     * @param null $url
     * @param array $options
     * @return ConsulResponse
     */
    public function get(string $url = null, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('GET', $url, $options);
    }

    /**
     * @param $url
     * @param array $options
     * @return ConsulResponse
     */
    public function head(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('HEAD', $url, $options);
    }

    /**
     * @param $url
     * @param array $options
     * @return ConsulResponse
     */
    public function delete(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('DELETE', $url, $options);
    }

    /**
     * @param $url
     * @param array $options
     * @return mixed|ConsulResponse
     */
    public function put(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('PUT', $url, $options);
    }

    /**
     * @param $url
     * @param array $options
     * @return mixed|ConsulResponse
     */
    public function patch(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('PATCH', $url, $options);
    }

    /**
     * @param $url
     * @param array $options
     * @return mixed|ConsulResponse
     */
    public function post(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('POST', $url, $options);
    }

    /**
     * @param $url
     * @param array $options
     * @return mixed|ConsulResponse
     */
    public function options(string $url, array $options = array(), string $driver = 'saber'): ResponseInterface
    {
        return $this->doRequest('OPTIONS', $url, $options);
    }

    /**
     * @param $method
     * @param $url
     * @param $options
     * @return ConsulResponse
     */
    protected function doRequest(string $method, string $url, array $options, string $driver = 'saber'): ResponseInterface
    {
        try {
            if ($driver === 'saber') {
                $options = array_merge($options, [
                    'method' => $method,
                    'uri' => $url,
                    'before' => function (Saber\Request $request) {
                        App::info(JsonHelper::encode($request->getUri()), 'http');
                    },
                    'after' => function (Saber\Response $response) {
                        App::info(JsonHelper::encode($response->getUri()), 'http');
                    }
                ]);
                $response = $this->getDriver($driver)->request($options);
            } else {
                $response = $this->getDriver($driver)->request($method, $url, $options);
            }
        } catch (\Exception $e) {
            $message = sprintf('Something went wrong when calling consul (%s).', $e->getMessage());

            $this->logger->error($message);

            throw new \RuntimeException($message);
        }

        if (400 <= $response->getStatusCode()) {
            $message = sprintf('Something went wrong when calling consul (%s - %s).', $response->getStatusCode(), $response->getReasonPhrase());

            $this->logger->error($message);

            $message .= "\n" . (string)$response->getBody();
            if (500 <= $response->getStatusCode()) {
                throw new \RuntimeException($message, $response->getStatusCode());
            }

            throw new \RuntimeException($message, $response->getStatusCode());
        }

        return $response;
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
