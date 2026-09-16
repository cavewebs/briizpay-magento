<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

/**
 * Checking that a webhook really came from BriizPay.
 *
 * Header: Briizpay-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256 of "t.body">
 *
 * The timestamp is inside the signed material, so a delivery captured off the
 * wire stays correctly signed and is refused on age instead. The same scheme
 * as briizpay-api src/lib/webhook-signature.ts and the WooCommerce plugin; the
 * unit test pins a vector so a change on either side fails loudly rather than
 * every store silently rejecting every payment notification.
 *
 * No Magento dependencies on purpose, so it runs in a plain unit test.
 */
class Signature
{
    public const HEADER = 'Briizpay-Signature';
    public const TOLERANCE_SECONDS = 300;

    public function verify(string $body, ?string $header, string $secret, ?int $now = null): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }
        $now = $now ?? time();

        $timestamp = null;
        $provided = null;
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = $pair[1];
            } elseif ($pair[0] === 'v1') {
                $provided = $pair[1];
            }
        }

        if ($timestamp === null || $provided === null || !ctype_digit($timestamp)) {
            return false;
        }
        // Refuses both a replayed old delivery and one dated in the future.
        if (abs($now - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        // hash_equals so the comparison does not leak the signature through
        // its timing.
        return hash_equals($expected, $provided);
    }
}
