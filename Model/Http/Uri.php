<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Psr\Http\Message\UriInterface;

/**
 * Absolute URL as a PSR-7 URI. Only the string form is used: the SDK builds
 * requests from Config.baseUrl plus a path.
 */
class Uri implements UriInterface
{
    public function __construct(private readonly string $uri)
    {
    }

    public function __toString(): string
    {
        return $this->uri;
    }

    public function getScheme(): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        return (string) (parse_url($this->uri, PHP_URL_SCHEME) ?? '');
    }

    public function getAuthority(): string
    {
        $host = $this->getHost();
        if ($host === '') {
            return '';
        }
        $user = $this->getUserInfo();
        $port = $this->getPort();
        $authority = $user !== '' ? $user . '@' . $host : $host;
        return $port !== null ? $authority . ':' . $port : $authority;
    }

    public function getUserInfo(): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        $user = parse_url($this->uri, PHP_URL_USER);
        if ($user === null || $user === false) {
            return '';
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        $pass = parse_url($this->uri, PHP_URL_PASS);
        return $pass !== null && $pass !== false ? $user . ':' . $pass : (string) $user;
    }

    public function getHost(): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        return strtolower((string) (parse_url($this->uri, PHP_URL_HOST) ?? ''));
    }

    public function getPort(): ?int
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        $port = parse_url($this->uri, PHP_URL_PORT);
        return is_int($port) ? $port : null;
    }

    public function getPath(): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        return (string) (parse_url($this->uri, PHP_URL_PATH) ?? '');
    }

    public function getQuery(): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        return (string) (parse_url($this->uri, PHP_URL_QUERY) ?? '');
    }

    public function getFragment(): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-7 over a string; Magento has no PSR-7 URI
        return (string) (parse_url($this->uri, PHP_URL_FRAGMENT) ?? '');
    }

    public function withScheme(string $scheme): UriInterface
    {
        return $this;
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        return $this;
    }

    public function withHost(string $host): UriInterface
    {
        return $this;
    }

    public function withPort(?int $port): UriInterface
    {
        return $this;
    }

    public function withPath(string $path): UriInterface
    {
        return $this;
    }

    public function withQuery(string $query): UriInterface
    {
        return $this;
    }

    public function withFragment(string $fragment): UriInterface
    {
        return $this;
    }
}
