<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** PSR-7 response Magento Curl turns a status and body into. */
class Response implements ResponseInterface
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        private readonly int $status,
        private readonly StreamInterface $body,
        private readonly array $headers = [],
        private readonly string $reason = '',
        private readonly string $protocol = '1.1'
    ) {
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): ResponseInterface
    {
        return new self($this->status, $this->body, $this->headers, $this->reason, $version);
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

    public function withHeader(string $name, $value): ResponseInterface
    {
        $headers = $this->headers;
        $headers[strtolower($name)] = is_array($value) ? array_values($value) : [(string) $value];
        return new self($this->status, $this->body, $headers, $this->reason, $this->protocol);
    }

    public function withAddedHeader(string $name, $value): ResponseInterface
    {
        $headers = $this->headers;
        $key = strtolower($name);
        $add = is_array($value) ? array_values($value) : [(string) $value];
        $headers[$key] = array_merge($headers[$key] ?? [], $add);
        return new self($this->status, $this->body, $headers, $this->reason, $this->protocol);
    }

    public function withoutHeader(string $name): ResponseInterface
    {
        $headers = $this->headers;
        unset($headers[strtolower($name)]);
        return new self($this->status, $this->body, $headers, $this->reason, $this->protocol);
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        return new self($this->status, $body, $this->headers, $this->reason, $this->protocol);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        return new self($code, $this->body, $this->headers, $reasonPhrase, $this->protocol);
    }

    public function getReasonPhrase(): string
    {
        return $this->reason;
    }
}
