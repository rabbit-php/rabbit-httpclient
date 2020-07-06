<?php
declare(strict_types=1);

namespace Rabbit\HttpClient;

use Rabbit\Base\App;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\UrlHelper;
use Swlib\Saber;
use Throwable;

/**
 * Class Client
 * @package rabbit\consul
 * @method Response get(string $url = null, array $options = array(), string $driver = 'saber'): Response
 * @method Response head(string $url = null, array $options = array(), string $driver = 'saber'): Response
 * @method Response put(string $url = null, array $options = array(), string $driver = 'saber'): Response
 * @method Response post(string $url = null, array $options = array(), string $driver = 'saber'): Response
 * @method Response patch(string $url = null, array $options = array(), string $driver = 'saber'): Response
 * @method Response delete(string $url = null, array $options = array(), string $driver = 'saber'): Response
 * @method Response options(string $url, array $options = array(), string $driver = 'saber') : Response
 */
class Client
{
    /** @var array */
    protected array $driver = [];
    /** @var string */
    private string $default;
    /** @var array */
    protected array $options = [];

    /**
     * Client constructor.
     * @param array $options
     * @param string $default
     * @param array $driver
     */
    public function __construct(array $options = array(), string $default = 'saber', bool $session = false, array $driver = [])
    {
        $this->options = $options;
        $this->parseOptions();
        if (empty($driver)) {
            $this->driver['guzzle'] = new \GuzzleHttp\Client($this->options);
            if (isset($this->options['auth']) && !isset($options['auth']['username'])) {
                $this->options['auth'] = [
                    'username' => $this->options['auth'][0],
                    'password' => $this->options['auth'][1]
                ];
            }
            if ($session) {
                $this->driver['saber'] = Saber::session($this->options);
                gc_collect_cycles();
            } else {
                $this->driver['saber'] = Saber::create($this->options);
            }
        } else {
            $this->driver = $driver;
        }
        $this->default = $default;
    }

    /**
     * @return void
     */
    private function parseOptions(): void
    {
        if (null === ($uri = ArrayHelper::getOneValue($this->options, ['uri', 'base_uri'])) || isset($this->options['auth'])) {
            return;
        }

        $parsed = parse_url($uri);
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? $parsed['pass'] : '';
        $this->options['base_uri'] = UrlHelper::unParseUrl($parsed, false);
        if (!empty($parsed['user'])) {
            $this->options['auth'] = [
                $user,
                $pass
            ];
        }
    }

    /**
     * @param $name
     * @param $args
     * @return Response
     * @throws Throwable
     */
    public function __call($name, $args)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri = $args[0];
        $opts = isset($args[1]) ? $args[1] : [];
        $driver = isset($args[2]) ? $args[2] : null;
        return $this->request(array_merge($opts, [
            'method' => $name,
            'uri' => $uri
        ]), $driver);
    }

    /**
     * @param array $options
     * @param string|null $driver
     * @return Response
     * @throws Throwable
     */
    public function request(array $options = [], string $driver = null): Response
    {
        try {
            $options = array_merge($this->options, $options);
            $driver = $driver ?? $this->default;
            if ($driver === 'saber') {
                if (isset($options['auth']) && !isset($options['auth']['username'])) {
                    $options['auth'] = [
                        'username' => $options['auth'][0],
                        'password' => $options['auth'][1]
                    ];
                }
                $response = $this->getDriver($driver)->request($options);
            } elseif ($driver === 'guzzle') {
                $method = ArrayHelper::getOneValue($options, ['method']);
                $uri = ArrayHelper::getOneValue($options, ['uri', 'base_uri']);
                $ext = [
                    'query' => ArrayHelper::getOneValue($options, ['uri_query', 'query'], null, true),
                    'save_to' => ArrayHelper::getOneValue($options, ['download_dir'], null, true)
                ];
                $response = $this->getDriver($driver)->request($method, $uri, array_filter(array_merge($options, $ext)));
            } else {
                throw new NotSupportedException('Not support the httpclient driver ' . $driver ?? $this->default);
            }
        } catch (Throwable $e) {
            if (!method_exists($e, 'getResponse') || (null === $response = $e->getResponse())) {
                $message = sprintf('Something went wrong (%s).', $e->getMessage());
                App::error($message, 'http');
                throw new \RuntimeException($message);
            }
        }

        if (400 <= $response->getStatusCode()) {
            $message = sprintf('Something went wrong (%s - %s).', $response->getStatusCode(),
                $response->getReasonPhrase());

            App::error($message, 'http');
            $body = (string)$response->getBody();
            $message .= "\n" . $body;
            if (500 <= $response->getStatusCode()) {
                throw new \RuntimeException($body, $response->getStatusCode());
            }

            throw new \RuntimeException($body, $response->getStatusCode());
        }

        return new Response($response);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws NotSupportedException
     */
    public function getDriver(string $name)
    {
        if (isset($this->driver[$name])) {
            return $this->driver[$name];
        }
        throw new NotSupportedException('Not support the httpclient driver ' . $name);
    }
}
