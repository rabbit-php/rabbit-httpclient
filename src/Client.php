<?php

declare(strict_types=1);

namespace Rabbit\HttpClient;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rabbit\Base\Exception\NotSupportedException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\UrlHelper;
use RuntimeException;
use Swlib\Saber;
use Swlib\Saber\ClientPool;
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
class Client implements ClientInterface
{
    private string $driver;
    protected array $configs = [];
    protected bool $session;
    protected Saber|GuzzleHttpClient $client;

    public function __construct(array $configs = [], string $driver = null, bool $session = false)
    {
        $this->driver = $driver ?? getDI('http.driver', false) ?? 'saber';
        $this->configs = $configs;
        $this->session = $session;
        switch ($this->driver) {
            case 'curl':
                $this->client = new GuzzleHttpClient();
                break;
            case 'guzzle':
                $handler = HandlerStack::create(create(StreamHandler::class));
                $this->client = new GuzzleHttpClient(['handler' => $handler]);
                break;
            case 'saber':
                if ($this->session) {
                    $this->client = Saber::session();
                } else {
                    $this->client = Saber::create();
                }
                break;
            default:
                throw new NotSupportedException('Not support the httpclient driver ' . $this->driver);
        };
    }

    public function getClient(): Saber|GuzzleHttpClient
    {
        return $this->client;
    }

    public function __call($name, $args)
    {
        if (count($args) < 1) {
            throw new \InvalidArgumentException('Magic request methods require a URI and optional options array');
        }

        $uri = $args[0];
        $opts = isset($args[1]) ? $args[1] : [];
        return $this->request([
            ...$opts,
            'method' => $name,
            'uri' => $uri
        ]);
    }

    public function request(array $configs = []): Response
    {
        $duration = -1;
        try {
            $configs = [...$this->configs, ...$configs];
            isset($configs['base_uri']) && ($configs['base_uri'] = (string)Utils::uriFor($configs['base_uri']));
            isset($configs['uri']) && ($configs['uri'] = (string)Utils::uriFor($configs['uri']));
            if ($this->driver === 'saber') {
                if (isset($configs['auth']) && !isset($configs['auth']['username'])) {
                    [$configs['auth']['username'], $configs['auth']['password']] = ArrayHelper::remove($configs, 'auth');
                }
                if (isset($configs['proxy']) && is_array($configs['proxy'])) {
                    $configs['proxy'] = current(array_values($configs['proxy']));
                }
                $request = $this->client->request($configs + [
                    'psr' => true,
                    'uri_query' => ArrayHelper::getOneValue($configs, ['uri_query', 'query'], null, true),
                    'data' => ArrayHelper::getOneValue($configs, ['data', 'body'], null, true)
                ]);
                if ($configs['target'] ?? false) {
                    unset($configs['target']);
                    $parsed = parse_url($configs['uri']);
                    $request->withRequestTarget(($parsed['path'] ?? '/') . (isset($parsed['query']) ? "?{$parsed['query']}" : ''));
                }
                $response = $request->exec()->recv();
                $duration = (int)($response->getTime() * 1000);
            } else {
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
                            $proxy = UrlHelper::unParseUrl(parse_url($proxy), true, $this->driver === 'guzzle' ? 'http' : '');
                        }
                    } elseif (is_string($configs['proxy'])) {
                        $configs['proxy'] = UrlHelper::unParseUrl(parse_url($configs['proxy']), true, $this->driver === 'guzzle' ? 'http' : '');
                    }
                }
                if (null !== $before = ArrayHelper::getOneValue($configs, ['before'], null, true)) {
                    $handler = $this->client->getConfig('handler');
                    $before = (array)$before;
                    foreach ($before as $middleware) {
                        $handler->push(Middleware::mapRequest($middleware));
                    }
                }
                $start = microtime(true) * 1000;
                $response = $this->client->request($method, $uri, array_filter([...$configs, ...$ext]));
                $duration = (int)(microtime(true) * 1000 - $start);
            }
        } catch (Throwable $e) {
            $message = sprintf('Something went wrong (%s).', $e->getMessage());
            if (!method_exists($e, 'getResponse') || (null === $response = $e->getResponse())) {
                throw new RuntimeException($message, $e->getCode());
            }
            if (method_exists($response, 'getTime')) {
                $duration = (int)($response->getTime() * 1000);
            } else {
                $duration = 0;
            }
        }

        return new Response($response, $duration);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->request([
            'method' => $request->getMethod(),
            'target' => $request->getRequestTarget(),
            'uri' => $request->getUri(),
            'headers' => $request->getHeaders(),
            'protocol' => $request->getProtocolVersion(),
            'body' => $request->getBody()
        ]);
    }

    public static function getPool(string $key): ?array
    {
        return ClientPool::getInstance()->getStatus($key);
    }

    public static function release(string $key): void
    {
        !empty($key) && ClientPool::getInstance()->release($key);
    }

    public static function getKey(array $arr): string
    {
        $str = '';
        if (isset($arr['http_proxy_host'])) {
            $user = $arr['http_proxy_user'] ?? '';
            $pass = $arr['http_proxy_password'] ?? '';
            $host = $arr['http_proxy_host'] ?? '';
            $port = $arr['http_proxy_port'] ?? '';
            $str = ":{$user}:{$pass}@{$host}:{$port}";
        }
        return "{$arr['host']}:{$arr['port']}{$str}";
    }
}
