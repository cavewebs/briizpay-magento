<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

use BriizPay\Sdk\Dto\CreatePaymentRequest;
use BriizPay\Sdk\Dto\CreateRefund;
use BriizPay\Sdk\Dto\PaymentRequest;
use BriizPay\Sdk\Dto\Refund;

/**
 * Talking to the BriizPay API through the PHP SDK.
 *
 * Store-scoped: each call uses that store's API key. The SDK client itself
 * is built per key, because Config is immutable.
 *
 * @api
 */
interface ClientInterface
{
    public function createPaymentRequest(CreatePaymentRequest $request, ?int $storeId = null): PaymentRequest;

    public function getPaymentRequest(string $id, ?int $storeId = null): PaymentRequest;

    public function createRefund(CreateRefund $request, ?int $storeId = null): Refund;

    public function cancelPaymentRequest(string $id, ?int $storeId = null): PaymentRequest;
}
