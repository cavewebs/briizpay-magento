<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Magento\Framework\HTTP\Client\CurlFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Magento's Curl client as PSR-18, with the 20s wait the module has always used.
 *
 * PSR-18 has no timeout of its own. Magento Curl does, and that is the HTTP
 * client every store already has.
 */
class CurlClient implements ClientInterface
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly int $timeout = 20
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->timeout);
        foreach ($request->getHeaders() as $name => $values) {
            $curl->addHeader((string) $name, implode(', ', $values));
        }

        $uri = (string) $request->getUri();
        try {
            if (strtoupper($request->getMethod()) === 'GET') {
                $curl->get($uri);
            } else {
                $curl->post($uri, (string) $request->getBody());
            }
        } catch (\Throwable $e) {
            throw new CurlNetworkException($e->getMessage(), $request, $e);
        }

        /** @var array<string, string|list<string>> $rawHeaders */
        $rawHeaders = method_exists($curl, 'getHeaders') ? (array) $curl->getHeaders() : [];
        $headers = [];
        foreach ($rawHeaders as $name => $value) {
            $headers[strtolower((string) $name)] = is_array($value) ? array_values($value) : [(string) $value];
        }

        return new Response(
            (int) $curl->getStatus(),
            new Stream((string) $curl->getBody()),
            $headers
        );
    }
}
