<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

/**
 * The pay by bank discount: its names and its arithmetic.
 *
 * Bank payments do not convert on their own. A card is a habit, and at the
 * moment of paying the customer needs a reason to pick something else; a
 * saving they can see is the reason that works. The merchant can afford it: a
 * card costs them a percentage of every order and BriizPay does not.
 *
 * Kept free of Magento so compute() can be pinned by Test/run.php with the
 * same cases as the WooCommerce plugin's tests/test-discount.php. The two
 * plugins promise merchants the same discount, so they work it out the same
 * way.
 */
class Discount
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    /**
     * The total's code in etc/sales.xml, the checkout's total segments and the
     * order totals blocks.
     *
     * Deliberately without the word "discount": Magento_SalesRule's checkout
     * summary titles its own line from the first total segment whose code
     * contains "discount", and a coupon line must never borrow this one's name.
     */
    public const TOTAL_CODE = 'briizpay_saving';

    /**
     * The column on quote_address, quote, sales_order, sales_invoice and
     * sales_creditmemo, with base_ in front for the base currency amount.
     * Stored as a negative number, the way Magento stores discount_amount.
     */
    public const FIELD = 'briizpay_discount_amount';
    public const BASE_FIELD = 'base_briizpay_discount_amount';

    /** What the customer and the merchant see, wherever the line appears. */
    public const LABEL = 'Pay by bank discount';

    /**
     * The discount for a basis amount, as a positive number.
     *
     * Never more than the basis: £5 off a £3 basket is £3 off, not a negative
     * order. An unknown type is treated as a percentage, like the WooCommerce
     * plugin, so a setting that went missing cannot turn 1% into £1.
     *
     * @param float|int|string $basis What the discount applies to, in pounds.
     * @param string $type 'percent' or 'fixed'.
     * @param float|int|string $amount Percent, or pounds for a fixed discount.
     * @param int $decimals Currency precision.
     */
    public static function compute(float|int|string $basis, string $type, float|int|string $amount, int $decimals = 2): float
    {
        $basis = (float) $basis;
        $amount = (float) $amount;
        if ($basis <= 0 || $amount <= 0) {
            return 0.0;
        }
        $discount = $type === self::TYPE_FIXED ? $amount : $basis * $amount / 100;
        $discount = min($discount, $basis);
        return round($discount, $decimals);
    }

    /**
     * How much of an order's discount goes on one invoice or credit memo.
     *
     * The discount belongs to the whole basket, so a document for part of it
     * takes the same part of the discount, and the last document takes what
     * is left, so that the pennies rounding leaves behind are not lost or
     * counted twice. Never more than what is left: an order's discount is
     * taken off its invoices once, and given back on its credit memos once.
     *
     * @param float $orderAmount The order's discount, positive.
     * @param float $alreadyTaken What earlier documents of the same kind took, positive.
     * @param bool $isLast This document completes the order's items.
     * @param float $part The document's items, after discounts, with tax.
     * @param float $whole The order's items, on the same terms.
     */
    public static function share(float $orderAmount, float $alreadyTaken, bool $isLast, float $part, float $whole, int $decimals = 2): float
    {
        $left = round($orderAmount - $alreadyTaken, $decimals);
        if ($left <= 0) {
            return 0.0;
        }
        if ($isLast) {
            return $left;
        }
        if ($part <= 0 || $whole <= 0) {
            return 0.0;
        }
        return min($left, round($orderAmount * min(1.0, $part / $whole), $decimals));
    }
}
