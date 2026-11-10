<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/** PSR-7 request the SDK builds and Magento Curl then sends. */
class Request implements RequestInterface
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        private readonly string $method,
        private readonly UriInterface $uri,
        private readonly StreamInterface $body,
        private readonly array $headers = [],
        private readonly string $protocol = '1.1'
    ) {
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): RequestInterface
    {
        return new self($this->method, $this->uri, $this->body, $this->headers, $version);
    }

    /** @return array<string, list<string>> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /** @return list<string> */
    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): RequestInterface
    {
        $headers = $this->headers;
        $headers[strtolower($name)] = is_array($value) ? array_values($value) : [(string) $value];
        return new self($this->method, $this->uri, $this->body, $headers, $this->protocol);
    }

    public function withAddedHeader(string $name, $value): RequestInterface
    {
        $headers = $this->headers;
        $key = strtolower($name);
        $add = is_array($value) ? array_values($value) : [(string) $value];
        $headers[$key] = array_merge($headers[$key] ?? [], $add);
        return new self($this->method, $this->uri, $this->body, $headers, $this->protocol);
    }

    public function withoutHeader(string $name): RequestInterface
    {
        $headers = $this->headers;
        unset($headers[strtolower($name)]);
        return new self($this->method, $this->uri, $this->body, $headers, $this->protocol);
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): RequestInterface
    {
        return new self($this->method, $this->uri, $body, $this->headers, $this->protocol);
    }

    public function getRequestTarget(): string
    {
        $target = $this->uri->getPath() ?: '/';
        $query = $this->uri->getQuery();
        return $query === '' ? $target : $target . '?' . $query;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        return $this; // the SDK never retargets a request
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        return new self($method, $this->uri, $this->body, $this->headers, $this->protocol);
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        return new self($this->method, $uri, $this->body, $this->headers, $this->protocol);
    }
}
