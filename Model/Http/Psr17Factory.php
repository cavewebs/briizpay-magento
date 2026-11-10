<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-17 factories Magento's Object Manager can inject.
 *
 * Magento ships Curl, not a PSR-17 implementation of its own. The SDK needs
 * both a request factory and a stream factory; this is both.
 */
class Psr17Factory implements RequestFactoryInterface, StreamFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        $uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
        return new Request($method, $uri, $this->createStream());
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-17 requires this; the SDK never opens a file
            $contents = is_file($filename) ? (string) file_get_contents($filename) : '';
        } catch (\Throwable) {
            $contents = '';
        }
        return new Stream($contents);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            return new Stream('');
        }
        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- PSR-17 requires this; the SDK never opens a resource
            $contents = (string) stream_get_contents($resource);
        } catch (\Throwable) {
            $contents = '';
        }
        return new Stream($contents);
    }
}
