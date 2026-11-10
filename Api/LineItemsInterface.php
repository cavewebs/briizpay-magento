<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

use Magento\Sales\Model\Order;

/**
 * The order's lines, in the form BriizPay puts on the receipt.
 *
 * @api
 */
interface LineItemsInterface
{
    /**
     * Lines for a Magento order, or null when there is nothing to list.
     *
     * @return list<array<string, mixed>>|null
     */
    public function forOrder(Order $order): ?array;

    /**
     * Rows for amounts taken off the order total, each named.
     *
     * @param array<string, float|int|string|null> $amounts label => pounds
     * @return list<array{name: string, quantity: int, gross: int, taxRateBps: int, sku: string}>
     */
    public function reductionRows(array $amounts): array;

    /**
     * Turn rows of name, quantity and pence into BriizPay lines that add up to
     * the total.
     *
     * @param list<array{name: string, quantity: float|int, gross: int, taxRateBps: int, sku?: string}> $rows
     * @return list<array<string, mixed>>|null
     */
    public function build(array $rows, int $totalMinor): ?array;

    /** A rate worked back from a gross and its tax, rounded to an integer percent. */
    public function rateBps(int $gross, int $tax): int;
}
