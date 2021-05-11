<?php

declare(strict_types=1);

namespace Rabbit\HttpClient;

use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\UrlHelper;
use RuntimeException;
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
    /** @var string */
    private string $default;
    /** @var array */
    protected array $configs = [];

    protected bool $session;

    /**
     * Client constructor.
     * @param array $configs
     * @param string $default
     * @param array $driver
     */
    public function __construct(array $configs = [], string $default = null, bool $session = false)
    {
        $this->default = $default ?? getDI('http.driver', false) ?? 'saber';
        $this->configs = $configs;
        $this->session = $session;
        $this->parseConfigs($this->configs);
    }

    public function getDriver(array $configs = null, string $default = null)
    {
        $default ??= $this->default;
        $configs ??= $this->configs;
        switch ($default) {
            case 'curl':
                $driver = new \GuzzleHttp\Client($configs);
                break;
            case 'guzzle':
                $handler = HandlerStack::create(create(StreamHandler::class));
                $driver = new \GuzzleHttp\Client($configs += ['handler' => $handler]);
                break;
            case 'saber':
                if ($this->session) {
                    $driver = Saber::session($configs);
                } else {
                    $driver = Saber::create($configs);
                }
                break;
            default:
                throw new NotSupportedException('Not support the httpclient driver ' . $this->default);
        };
        return $driver;
    }

    /**
     * @return void
     */
    private function parseConfigs(array &$configs): void
    {
        if (null === ($uri = ArrayHelper::getOneValue($configs, ['uri', 'base_uri'])) || isset($configs['auth'])) {
            return;
        }

        $parsed = parse_url($uri);
        $user = isset($parsed['user']) ? $parsed['user'] : '';
        $pass = isset($parsed['pass']) ? $parsed['pass'] : '';
        $configs['base_uri'] = UrlHelper::unParseUrl($parsed, false);
        if (!empty($parsed['user'])) {
            $configs['auth'] = [
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
    public function request(array $configs = [], string $driver = null): Response
    {
        try {
            $configs = array_merge($this->configs, $configs);
            $driver = $driver ?? $this->default;
            $duration = -1;
            if ($driver === 'saber') {
                if (isset($configs['auth']) && !isset($configs['auth']['username'])) {
                    [$configs['auth']['username'], $configs['auth']['password']] = ArrayHelper::remove($configs, 'auth');
                }
                if (isset($configs['proxy']) && is_array($configs['proxy'])) {
                    $configs['proxy'] = current(array_values($configs['proxy']));
                }
                if ($configs['target'] ?? false) {
                    unset($configs['target']);
                    $parsed = parse_url($configs['uri']);
                    $request = $this->getDriver($configs, $driver)->request([
                        'psr' => true,
                        'uri_query' => ArrayHelper::getOneValue($configs, ['uri_query', 'query'], null, true),
                        'data' => ArrayHelper::getOneValue($configs, ['data', 'body'], null, true)
                    ])->withRequestTarget($parsed['path'] . '?' . $parsed['query']);
                    $request->exec();
                    $response = $request->recv();
                } else {
                    $response = $this->getDriver($configs, $driver)->request([
                        'uri_query' => ArrayHelper::getOneValue($configs, ['uri_query', 'query'], null, true),
                        'data' => ArrayHelper::getOneValue($configs, ['data', 'body'], null, true)
                    ]);
                }
                $duration = (int)($response->getTime() * 1000);
            } elseif ($driver === 'guzzle' || $driver === 'curl') {
                $method = ArrayHelper::getOneValue($configs, ['method']);
                $uri = ArrayHelper::getOneValue($configs, ['uri', 'base_uri']);
                $ext = [
                    'query' => ArrayHelper::getOneValue($configs, ['uri_query', 'query'], null, true),
                    'save_to' => ArrayHelper::getOneValue($configs, ['download_dir'], null, true),
                    'body' => ArrayHelper::getOneValue($configs, ['data', 'body'], null, true)
                ];
                if (isset($configs['proxy'])) {
                    if (is_array($configs['proxy'])) {
                        foreach ($configs['proxy'] as &$proxy) {
                            $proxy = UrlHelper::unParseUrl(parse_url($proxy), true, $driver === 'guzzle' ? 'http' : '');
                        }
                    } elseif (is_string($configs['proxy'])) {
                        $configs['proxy'] = UrlHelper::unParseUrl(parse_url($configs['proxy']), true, $driver === 'guzzle' ? 'http' : '');
                    }
                }
                $client = $this->getDriver($configs, $driver);
                if (null !== $before = ArrayHelper::getOneValue($configs, ['before'], null, true)) {
                    $handler = $client->getConfig('handler');
                    $before = (array)$before;
                    foreach ($before as $middleware) {
                        $handler->push(Middleware::mapRequest($middleware));
                    }
                }
                $start = microtime(true) * 1000;
                $response = $client->request($method, $uri, array_filter(array_merge($configs, $ext)));
                $duration = (int)(microtime(true) * 1000 - $start);
            } else {
                throw new NotSupportedException('Not support the httpclient driver ' . $driver ?? $this->default);
            }
        } catch (Throwable $e) {
            $message = sprintf('Something went wrong (%s).', $e->getMessage());
            if (!method_exists($e, 'getResponse') || (null === $response = $e->getResponse())) {
                throw new RuntimeException($message, 500);
            } else {
                throw new RuntimeException($message, $e->getCode());
            }
        }

        $code = $response->getStatusCode();
        if (2 !== ($code / 100) % 10) {
            $message = sprintf(
                'Something went wrong (%s - %s).',
                $code,
                $response->getReasonPhrase()
            );
            $body = $response->getBody();
            $message .= ($body->getSize() < 256 ? $body->getContents() : '');
            throw new RuntimeException($message, $code);
        }

        return new Response($response, $duration);
    }
}
