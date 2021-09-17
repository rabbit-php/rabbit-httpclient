<?php

declare(strict_types=1);

namespace Rabbit\HttpClient;


use DOMDocument;
use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Swlib\Util\DataParser;

/**
 * Class Response
 * @package Rabbit\HttpClient
 */
class Response implements ResponseInterface
{
    private ResponseInterface $response;

    private int $duration;

    public function __construct(ResponseInterface $response, int $duration)
    {
        $this->response = $response;
        $this->duration = $duration;
    }

    public function __get($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this->response, $getter)) {
            return $this->response->$getter();
        }
        if (isset($this->response->$name)) {
            return $this->response->$name;
        }
        return null;
    }

    public function getProtocolVersion()
    {
        return $this->response->getProtocolVersion();
    }

    public function withProtocolVersion($version)
    {
        $this->response->withProtocolVersion($version);
        return $this;
    }

    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    public function hasHeader($name)
    {
        return $this->response->hasHeader($name);
    }

    public function getHeader($name)
    {
        return $this->response->getHeader($name);
    }

    public function getHeaderLine($name)
    {
        return $this->response->getHeaderLine($name);
    }

    public function withHeader($name, $value)
    {
        $this->response->withHeader($name, $value);
        return $this;
    }

    public function withAddedHeader($name, $value)
    {
        $this->response->withAddedHeader($name, $value);
        return $this;
    }

    public function withoutHeader($name)
    {
        $this->response->withoutHeader($name);
        return $this;
    }

    public function getBody()
    {
        return $this->response->getBody();
    }

    public function withBody(StreamInterface $body)
    {
        $this->response->withBody($body);
        return $this;
    }

    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        $this->response->withStatus($code, $reasonPhrase);
        return $this;
    }

    public function getReasonPhrase()
    {
        return $this->response->getReasonPhrase();
    }

    public function jsonArray(): array
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToJsonArray($data);
    }

    public function jsonObject(): object
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToJsonObject($data);
    }

    public function xmlArray(): array
    {
        $data = (string)$this->response->getBody();
        return json_decode(
            json_encode(
                simplexml_load_string(
                    $data,
                    "SimpleXMLElement",
                    LIBXML_NOCDATA
                )
            ),
            true
        );
    }

    public function xmlObject(): object
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToXmlObject($data);
    }

    public function domObject(): DOMDocument
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToDomObject($data);
    }

    public function xpathObject(): DOMXPath
    {
        $data = (string)$this->response->getBody();
        $dom = DataParser::stringToDomObject($data);
        $dom->normalize();
        return new DOMXPath($dom);
    }

    public function getDuration(): int
    {
        return $this->duration;
    }
}
