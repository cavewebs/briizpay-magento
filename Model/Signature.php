<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use BriizPay\Sdk\Contract\WebhookVerifierInterface;
use BriizPay\Sdk\Dto\WebhookEvent;
use BriizPay\Sdk\Webhook\WebhookSignature;

/**
 * Checking that a webhook really came from BriizPay.
 *
 * Delegates to the SDK verifier (HMAC-SHA256 of timestamp.body, 300s skew).
 * The Magento class stays so Magento can inject and intercept it.
 *
 * @api
 */
class Signature
{
    public const HEADER = WebhookSignature::HEADER;
    public const TOLERANCE_SECONDS = WebhookSignature::TOLERANCE_SECONDS;

    public function __construct(
        private readonly WebhookVerifierInterface $verifier
    ) {
    }

    public function verify(string $body, ?string $header, string $secret, ?int $now = null): bool
    {
        return $this->verifier->verify($body, $header, $secret, $now);
    }

    public function parse(string $body, ?string $header, string $secret, ?int $now = null): WebhookEvent
    {
        return $this->verifier->parse($body, $header, $secret, $now);
    }
}
