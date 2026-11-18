<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use BriizPay\Sdk\Contract\WebhookVerifierInterface;
use BriizPay\Sdk\Dto\WebhookEvent;
use BriizPay\Sdk\Webhook\WebhookSignature;

/**
 * Magento type for the SDK webhook verifier.
 *
 * Magento's DI compiler only accepts a preference whose class belongs to a
 * Magento module. The SDK's WebhookSignature is final, readonly, and not a
 * module class, so compile refuses it. This adapter implements the same
 * interface and delegates to a WebhookSignature it constructs itself.
 *
 * @api
 */
class WebhookVerifier implements WebhookVerifierInterface
{
    private readonly WebhookSignature $signature;

    public function __construct()
    {
        $this->signature = new WebhookSignature();
    }

    public function verify(string $payload, ?string $signatureHeader, string $secret, ?int $now = null): bool
    {
        return $this->signature->verify($payload, $signatureHeader, $secret, $now);
    }

    public function parse(string $payload, ?string $signatureHeader, string $secret, ?int $now = null): WebhookEvent
    {
        return $this->signature->parse($payload, $signatureHeader, $secret, $now);
    }
}
