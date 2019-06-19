<?php

namespace rabbit\httpclient;

use GuzzleHttp\Handler\StreamHandler;
use rabbit\App;
use rabbit\exception\NotSupportedException;
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
        $this->parseOptions($options);
        if (empty($driver)) {
            $options['handler'] = new StreamHandler();
            $this->driver['guzzle'] = new \GuzzleHttp\Client($options);
            if (isset($options['auth']) && !isset($options['auth']['username'])) {
                $options['auth'] = [
                    'username' => $options['auth'][0],
                    'password' => $options['auth'][1]
                ];
            }
            $this->driver['saber'] = Saber::create($options);
        } else {
            $this->driver = $driver;
        }
        $this->default = $default;
    }

    /**
     * @param array $options
     * @param string $driver
     * @return array
     */
    private function parseOptions(array &$options): void
    {
        if (!isset($options['base_uri']) || isset($options['auth'])) {
            return;
        }
        $parsed = parse_url($options['base_uri']);
        if (!isset($parsed['path'])) {
            $parsed['path'] = '/';
        }
        $user = !empty($parsed['user']) ? $parsed['user'] : '';
        $pwd = !empty($parsed['pass']) ? $parsed['pass'] : '';
        $options['base_uri'] = (isset($parsed['scheme']) ? $parsed['scheme'] : $defaultScheme)
            . '://'
            . $parsed['host']
            . (!empty($parsed['port']) ? ':' . $parsed['port'] : '')
            . $parsed['path']
            . '?'
            . $parsed['query'];
        if (!empty($user)) {
            $options['auth'] = [
                $user,
                $pwd
            ];
        }
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
            if (!empty($url)) {
                $options['base_uri'] = $url;
                $this->parseOptions($options);
                $url = $options['base_uri'];
                unset($options['base_uri']);
            }
            if ($driver === 'saber' || ($driver = $this->default) === 'saber') {
                $options = array_merge($options, array_filter([
                    'method' => $method,
                    'uri' => $url
                ]));
                if (isset($options['auth']) && !isset($options['auth']['username'])) {
                    $options['auth'] = [
                        'username' => $options['auth'][0],
                        'password' => $options['auth'][1]
                    ];
                }
                $response = $this->getDriver($driver)->request($options);
            } elseif ($driver === 'guzzle' || ($driver = $this->default) === 'guzzle') {
                $response = $this->getDriver($driver)->request($method, $url, $options);
            } else {
                throw new NotSupportedException('Not support the httpclient driver ' . $driver ?? $this->default);
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
        if (isset($this->driver[$name])) {
            return $this->driver[$name];
        }
        throw new NotSupportedException('Not support the httpclient driver ' . $driver ?? $this->default);;
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
}
