<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

/**
 * Talking to the BriizPay API.
 *
 * The implementation stays array-based until the standalone PHP SDK lands.
 * Callers depend on this contract so that swap is a preference change in
 * di.xml rather than a rewrite of every payment, refund and cancel path.
 *
 * @api
 */
interface ClientInterface
{
    /**
     * @param array<string, mixed> $body amountMinor (pence), currency, externalReference,
     *                                   customerName, customerEmail, memo, returnUrl
     * @return array<string, mixed> the payment request, including id and payUrl
     */
    public function createPaymentRequest(array $body, ?int $storeId = null): array;

    /** @return array<string, mixed> */
    public function getPaymentRequest(string $id, ?int $storeId = null): array;

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function createRefund(array $body, ?int $storeId = null): array;

    /** @return array<string, mixed> */
    public function cancelPaymentRequest(string $id, ?int $storeId = null): array;
}
