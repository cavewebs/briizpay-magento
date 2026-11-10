<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Api;

use BriizPay\PayByBank\Api\ClientInterface;
use BriizPay\PayByBank\Model\Config;
use BriizPay\Sdk\ClientInterface as SdkClientInterface;
use BriizPay\Sdk\Dto\CreatePaymentRequest;
use BriizPay\Sdk\Dto\CreateRefund;
use BriizPay\Sdk\Dto\PaymentRequest;
use BriizPay\Sdk\Dto\Refund;
use BriizPay\Sdk\Exception\AuthenticationException;
use BriizPay\Sdk\Exception\BriizPayException;
use BriizPay\Sdk\Exception\NetworkException;
use Psr\Log\LoggerInterface;

/**
 * Magento face of the BriizPay PHP SDK.
 *
 * Builds a store-scoped SDK client, maps typed exceptions to ApiException
 * with a message fit for a shopper, and logs the rest.
 *
 * @api
 */
class Client implements ClientInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly SdkClientFactory $sdkFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createPaymentRequest(CreatePaymentRequest $request, ?int $storeId = null): PaymentRequest
    {
        try {
            return $this->sdk($storeId)->createPaymentRequest($request);
        } catch (BriizPayException $e) {
            throw $this->wrap($e);
        }
    }

    public function getPaymentRequest(string $id, ?int $storeId = null): PaymentRequest
    {
        try {
            return $this->sdk($storeId)->getPaymentRequest($id);
        } catch (BriizPayException $e) {
            throw $this->wrap($e);
        }
    }

    public function createRefund(CreateRefund $request, ?int $storeId = null): Refund
    {
        try {
            return $this->sdk($storeId)->createRefund($request);
        } catch (BriizPayException $e) {
            throw $this->wrap($e);
        }
    }

    public function cancelPaymentRequest(string $id, ?int $storeId = null): PaymentRequest
    {
        try {
            return $this->sdk($storeId)->cancelPaymentRequest($id);
        } catch (BriizPayException $e) {
            throw $this->wrap($e);
        }
    }

    private function sdk(?int $storeId): SdkClientInterface
    {
        if (!$this->config->hasValidApiKey($storeId)) {
            throw new ApiException(__('BriizPay is not set up yet. Please choose another payment method.'));
        }
        return $this->sdkFactory->create($this->config->getApiKey($storeId));
    }

    private function wrap(BriizPayException $e): ApiException
    {
        if ($e instanceof NetworkException) {
            $this->logger->error('[BriizPay] Request failed: ' . $e->getMessage());
            return new ApiException(__('Could not reach BriizPay. Please try again.'));
        }

        $this->logger->error(sprintf('[BriizPay] HTTP %d: %s', $e->statusCode, $e->getMessage()));

        if ($e instanceof AuthenticationException && $e->statusCode === 401) {
            return new ApiException(
                __('BriizPay rejected the API key. Please choose another payment method.'),
                401
            );
        }

        $message = $e->getMessage() !== ''
            ? $e->getMessage()
            : (string) __('BriizPay could not take this payment right now.');

        // phpcs:ignore Magento2.Translation.ConstantUsage -- BriizPay's own English, or the fallback literal above
        return new ApiException(__($message), $e->statusCode);
    }
}
