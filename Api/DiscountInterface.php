<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

/**
 * The pay by bank discount: its arithmetic, not its Magento totals wiring.
 *
 * @api
 */
interface DiscountInterface
{
    /**
     * The discount for a basis amount, as a positive number.
     *
     * @param float|int|string $basis What the discount applies to, in pounds.
     * @param string $type 'percent' or 'fixed'.
     * @param float|int|string $amount Percent, or pounds for a fixed discount.
     * @param int $decimals Currency precision.
     */
    public function compute(
        float|int|string $basis,
        string $type,
        float|int|string $amount,
        int $decimals = 2
    ): float;

    /**
     * How much of an order's discount goes on one invoice or credit memo.
     *
     * @param float $orderAmount The order's discount, positive.
     * @param float $alreadyTaken What earlier documents of the same kind took, positive.
     * @param bool $isLast This document completes the order's items.
     * @param float $part The document's items, after discounts, with tax.
     * @param float $whole The order's items, on the same terms.
     */
    public function share(
        float $orderAmount,
        float $alreadyTaken,
        bool $isLast,
        float $part,
        float $whole,
        int $decimals = 2
    ): float;
}
