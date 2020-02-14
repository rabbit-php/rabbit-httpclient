<?php

namespace rabbit\httpclient;

use rabbit\App;
use rabbit\exception\NotSupportedException;
use rabbit\helper\ArrayHelper;
use Swlib\Saber;

/**
 * Class Client
 * @package rabbit\consul
 * @method Response get(string $url = null, array $options = array(), string $driver = 'saber')
 * @method Response head(string $url = null, array $options = array(), string $driver = 'saber')
 * @method Response put(string $url = null, array $options = array(), string $driver = 'saber')
 * @method Response post(string $url = null, array $options = array(), string $driver = 'saber')
 * @method Response patch(string $url = null, array $options = array(), string $driver = 'saber')
 * @method Response delete(string $url = null, array $options = array(), string $driver = 'saber')
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
        if (null === $uri = ArrayHelper::getOneValue($options, ['base_uri', 'uri']) || isset($options['auth'])) {
            return;
        }
        $parsed = parse_url($uri);
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
     * @param $name
     * @param $arguments
     * @return Response
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri = $args[0];
        $opts = isset($args[1]) ? $args[1] : [];
        $driver = isset($args[2]) ? $args[2] : null;
        return $this->request(array_merge($options, [
            'method' => $name,
            'uri' => $url
        ]), $driver);
    }

    /**
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws \Exception
     */
    public function request(array $options, string $driver = null): Response
    {
        try {
            if ($driver === 'saber' || ($driver = $this->default) === 'saber') {
                if (isset($options['auth']) && !isset($options['auth']['username'])) {
                    $options['auth'] = [
                        'username' => $options['auth'][0],
                        'password' => $options['auth'][1]
                    ];
                }
                $response = $this->getDriver($driver)->request($options);
            } elseif ($driver === 'guzzle' || ($driver = $this->default) === 'guzzle') {
                $method = ArrayHelper::getOneValue($options, ['method']);
                $uri = ArrayHelper::getOneValue($options, ['base_uri', 'uri']);
                $ext = array_filter([
                    'query' => ArrayHelper::getOneValue($options, ['uri_query', 'query'], null, true),
                    'save_to' => ArrayHelper::getOneValue($options, ['download_dir'], null, true)
                ]);
                $response = $this->getDriver($driver)->request($method, $url, array_merge($options, $ext));
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
}
