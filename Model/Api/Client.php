<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Api;

use BriizPay\PayByBank\Model\Config;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Talking to the BriizPay API.
 *
 * Every call goes to /v1, the versioned public surface, never to anything the
 * BriizPay dashboard uses. Those change with the dashboard, and a module sitting
 * in stores that update when they choose cannot ship in step with them.
 *
 * Failures are turned into ApiException with a message fit for a shopper; the
 * real response goes to the log.
 */
class Client
{
    private const LIVE_BASE = 'https://api.briizpay.com';
    private const TEST_BASE = 'https://dev.api.briizpay.com';

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $body amountMinor (pence), currency, externalReference,
     *                                   customerName, customerEmail, memo, returnUrl
     * @return array<string, mixed> the payment request, including id and payUrl
     */
    public function createPaymentRequest(array $body, ?int $storeId = null): array
    {
        return $this->request('POST', '/v1/payment-requests', $body, $storeId);
    }

    /** @return array<string, mixed> */
    public function getPaymentRequest(string $id, ?int $storeId = null): array
    {
        return $this->request('GET', '/v1/payment-requests/' . rawurlencode($id), null, $storeId);
    }

    /**
     * Stop a payment being payable, so a customer who wanders back to a stale
     * link cannot pay for an order the store has dropped.
     *
     * @return array<string, mixed>
     */
    public function cancelPaymentRequest(string $id, ?int $storeId = null): array
    {
        return $this->request('POST', '/v1/payment-requests/' . rawurlencode($id) . '/cancel', new \stdClass(), $storeId);
    }

    /**
     * @param array<string, mixed>|\stdClass|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array|\stdClass|null $body, ?int $storeId): array
    {
        if (!$this->config->hasValidApiKey($storeId)) {
            throw new ApiException(__('BriizPay is not set up yet. Please choose another payment method.'));
        }

        $key = $this->config->getApiKey($storeId);
        $base = $this->config->isTestKey($storeId) ? self::TEST_BASE : self::LIVE_BASE;

        $curl = $this->curlFactory->create();
        $curl->setTimeout(20);
        $curl->addHeader('Authorization', 'Bearer ' . $key);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('User-Agent', 'BriizPay-Magento/1.0.0');

        try {
            if ($method === 'GET') {
                $curl->get($base . $path);
            } else {
                $curl->post($base . $path, $this->json->serialize($body ?? new \stdClass()));
            }
        } catch (\Throwable $e) {
            $this->logger->error('[BriizPay] Request failed: ' . $e->getMessage());
            throw new ApiException(__('Could not reach BriizPay. Please try again.'));
        }

        $status = (int) $curl->getStatus();
        $raw = (string) $curl->getBody();
        try {
            $parsed = $raw === '' ? [] : $this->json->unserialize($raw);
        } catch (\Throwable) {
            $parsed = [];
        }
        $parsed = is_array($parsed) ? $parsed : [];

        if ($status >= 200 && $status < 300) {
            return isset($parsed['data']) && is_array($parsed['data']) ? $parsed['data'] : $parsed;
        }

        $this->logger->error(sprintf('[BriizPay] HTTP %d from %s: %s', $status, $path, $raw));

        if ($status === 401) {
            throw new ApiException(__('BriizPay rejected the API key. Please choose another payment method.'));
        }

        // The API answers errors as { msg }, written for people. Anything else
        // is infrastructure talking and not for a shopper.
        $message = isset($parsed['msg']) && is_string($parsed['msg'])
            ? $parsed['msg']
            : (string) __('BriizPay could not take this payment right now.');

        throw new ApiException(__($message), $status);
    }
}
