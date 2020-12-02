<?php

declare(strict_types=1);

namespace Rabbit\HttpClient;

use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Rabbit\Base\App;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\UrlHelper;
use Swlib\Saber;
use Throwable;

/**
 * Class Client
 * @package rabbit\consul
 * @method Response get(string $url = null, array $configs = array(), string $driver = 'saber'): Response
 * @method Response head(string $url = null, array $configs = array(), string $driver = 'saber'): Response
 * @method Response put(string $url = null, array $configs = array(), string $driver = 'saber'): Response
 * @method Response post(string $url = null, array $configs = array(), string $driver = 'saber'): Response
 * @method Response patch(string $url = null, array $configs = array(), string $driver = 'saber'): Response
 * @method Response delete(string $url = null, array $configs = array(), string $driver = 'saber'): Response
 * @method Response options(string $url, array $configs = array(), string $driver = 'saber') : Response
 */
class Client
{
    protected $driver;
    /** @var string */
    private string $default;
    /** @var array */
    protected array $configs = [];

    /**
     * Client constructor.
     * @param array $configs
     * @param string $default
     * @param array $driver
     */
    public function __construct(array $configs = array(), string $default = 'saber', bool $session = false)
    {
        $this->parseConfigs();
        switch ($default) {
            case 'guzzle':
                $configs = array_merge($configs, ['handler' => HandlerStack::create(create(StreamHandler::class))]);
                $this->driver = new \GuzzleHttp\Client($configs);
                break;
            case 'saber':
                if (isset($configs['auth']) && !isset($configs['auth']['username'])) {
                    $configs['auth'] = [
                        'username' => $configs['auth'][0],
                        'password' => $configs['auth'][1]
                    ];
                }
                if ($session) {
                    $this->driver = Saber::session($configs);
                    gc_collect_cycles();
                } else {
                    $this->driver = Saber::create($configs);
                }
                break;
            default:
                throw new NotSupportedException('Not support the httpclient driver ' . $default);
        }
        $this->default = $default;
        $this->configs = $configs;
    }

    /**
     * @return void
     */
    private function parseConfigs(): void
    {
        if (null === ($uri = ArrayHelper::getOneValue($this->configs, ['uri', 'base_uri'])) || isset($this->configs['auth'])) {
            return;
        }

        $parsed = parse_url($uri);
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? $parsed['pass'] : '';
        $this->configs['base_uri'] = UrlHelper::unParseUrl($parsed, false);
        if (!empty($parsed['user'])) {
            $this->configs['auth'] = [
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
     * @param array $configs
     * @param string|null $driver
     * @return Response
     * @throws Throwable
     */
    public function request(array $configs = []): Response
    {
        try {
            $configs = array_merge($this->configs, $configs);
            $driver = $driver ?? $this->default;
            if ($driver === 'saber') {
                if (isset($configs['auth']) && !isset($configs['auth']['username'])) {
                    $configs['auth'] = [
                        'username' => $configs['auth'][0],
                        'password' => $configs['auth'][1]
                    ];
                }
                $response = $this->driver->request($configs);
            } elseif ($driver === 'guzzle') {
                $method = ArrayHelper::getOneValue($configs, ['method']);
                $uri = ArrayHelper::getOneValue($configs, ['uri', 'base_uri']);
                $ext = [
                    'query' => ArrayHelper::getOneValue($configs, ['uri_query', 'query'], null, true),
                    'save_to' => ArrayHelper::getOneValue($configs, ['download_dir'], null, true)
                ];
                $response = $this->driver->request($method, $uri, array_filter(array_merge($configs, $ext)));
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
            $message = sprintf(
                'Something went wrong (%s - %s).',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            );

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
}
