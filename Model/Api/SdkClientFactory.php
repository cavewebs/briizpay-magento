<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Api;

use BriizPay\Sdk\Client;
use BriizPay\Sdk\ClientInterface as SdkClientInterface;
use BriizPay\Sdk\Config as SdkConfig;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * One SDK client for one API key.
 *
 * Magento keys are store-scoped. The SDK Config is immutable, so a call for
 * store 2 cannot reuse the client built for store 1.
 */
class SdkClientFactory
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    public function create(string $apiKey): SdkClientInterface
    {
        return new Client(
            SdkConfig::fromApiKey($apiKey),
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }
}
