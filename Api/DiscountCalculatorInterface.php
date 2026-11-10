<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * The pay by bank discount for a basket, from the merchant's settings.
 *
 * @api
 */
interface DiscountCalculatorInterface
{
    /**
     * The discount this basket earns if it is paid by bank, whatever method is
     * chosen right now.
     *
     * @param iterable<AbstractItem> $items
     * @return array{0: float, 1: float}
     */
    public function offerFor(iterable $items, ?int $storeId): array;

    /**
     * The discount this basket gets: the offer when the quote's payment method
     * is BriizPay, nothing otherwise.
     *
     * @param iterable<AbstractItem|CartItemInterface> $items
     * @return array{0: float, 1: float}
     */
    public function amountFor(Quote $quote, iterable $items): array;

    public function isPaidByBank(Quote $quote): bool;

    /**
     * What the discount applies to: the items after coupons, with their tax,
     * without shipping.
     *
     * @param iterable<AbstractItem> $items
     * @return array{0: float, 1: float}
     */
    public function basis(iterable $items): array;
}
