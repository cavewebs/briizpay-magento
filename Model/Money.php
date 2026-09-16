<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

/**
 * Pounds to pence, once, in one place.
 *
 * The API takes whole pence. A float total like 19.99 is 1998.9999... in
 * binary, so it is rounded, never truncated, or an order would be requested a
 * penny short and then held as an underpayment when it was paid in full.
 */
class Money
{
    public static function toMinor(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
