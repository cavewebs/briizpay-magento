<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

/**
 * Convert a store amount in pounds to whole pence for the BriizPay API.
 *
 * @api
 */
interface MoneyInterface
{
    public function toMinor(float|string $amount): int;
}
