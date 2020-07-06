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
 * @package rabbit\httpclient
 */
class Response implements ResponseInterface
{
    /** @var ResponseInterface */
    private ResponseInterface $response;

    /**
     * Response constructor.
     * @param ResponseInterface $response
     */
    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getProtocolVersion()
    {
        return $this->response->getProtocolVersion();
    }

    /**
     * @param string $version
     * @return $this|ResponseInterface
     */
    public function withProtocolVersion($version)
    {
        $this->response->withProtocolVersion($version);
        return $this;
    }

    /**
     * @return \string[][]
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasHeader($name)
    {
        return $this->response->hasHeader($name);
    }

    /**
     * @param string $name
     * @return string[]|void
     */
    public function getHeader($name)
    {
        return $this->response->getHeader($name);
    }

    /**
     * @param string $name
     * @return string
     */
    public function getHeaderLine($name)
    {
        return $this->response->getHeaderLine($name);
    }

    /**
     * @param string $name
     * @param string|string[] $value
     * @return $this|ResponseInterface
     */
    public function withHeader($name, $value)
    {
        $this->response->withHeader($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @param string|string[] $value
     * @return $this|ResponseInterface
     */
    public function withAddedHeader($name, $value)
    {
        $this->response->withAddedHeader($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @return $this|ResponseInterface
     */
    public function withoutHeader($name)
    {
        $this->response->withoutHeader($name);
        return $this;
    }

    /**
     * @return StreamInterface
     */
    public function getBody()
    {
        return $this->response->getBody();
    }

    /**
     * @param StreamInterface $body
     * @return $this|ResponseInterface
     */
    public function withBody(StreamInterface $body)
    {
        $this->response->withBody($body);
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->response->getStatusCode();
    }

    /**
     * @param int $code
     * @param string $reasonPhrase
     * @return $this|ResponseInterface
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $this->response->withStatus($code, $reasonPhrase);
        return $this;
    }

    /**
     * @return string
     */
    public function getReasonPhrase()
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * @return array
     */
    public function jsonArray(): array
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToJsonArray($data);
    }

    /**
     * @return object
     */
    public function jsonObject(): object
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToJsonObject($data);
    }

    /**
     * @return array
     */
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

    /**
     * @return object
     */
    public function xmlObject(): object
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToXmlObject($data);
    }

    /**
     * @return DOMDocument
     */
    public function domObject(): DOMDocument
    {
        $data = (string)$this->response->getBody();
        return DataParser::stringToDomObject($data);
    }

    /**
     * @return DOMXPath
     */
    public function xpathObject(): DOMXPath
    {
        $data = (string)$this->response->getBody();
        $dom = DataParser::stringToDomObject($data);
        $dom->normalize();
        return new DOMXPath($dom);
    }
}