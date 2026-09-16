<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * The pay by bank discount for a basket, from the merchant's settings.
 *
 * One place for the question, so the totals collector that applies the
 * discount, the guard that checks it before an order is placed and the saving
 * shown next to the method name at checkout can never disagree.
 */
class DiscountCalculator
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * The discount this basket earns if it is paid by bank, whatever method is
     * chosen right now. Positive, in the quote currency and the base currency.
     *
     * @param iterable<AbstractItem> $items the items of the address being totalled
     * @return array{0: float, 1: float}
     */
    public function offerFor(iterable $items, ?int $storeId): array
    {
        if (!$this->config->isDiscountOffered($storeId)) {
            return [0.0, 0.0];
        }
        $type = $this->config->getDiscountType($storeId);
        $amount = $this->config->getDiscountAmount($storeId);
        [$basis, $baseBasis] = $this->basis($items);

        return [
            Discount::compute($basis, $type, $amount),
            Discount::compute($baseBasis, $type, $amount),
        ];
    }

    /**
     * The discount this basket gets: the offer when the quote's payment method
     * is BriizPay, nothing otherwise.
     *
     * @param iterable<AbstractItem> $items
     * @return array{0: float, 1: float}
     */
    public function amountFor(Quote $quote, iterable $items): array
    {
        if (!self::isPaidByBank($quote)) {
            return [0.0, 0.0];
        }
        return $this->offerFor($items, (int) $quote->getStoreId());
    }

    public static function isPaidByBank(Quote $quote): bool
    {
        return $quote->getPayment()->getMethod() === Config::METHOD_CODE;
    }

    /**
     * What the discount applies to: the items after coupons, with their tax,
     * without shipping. Shipping is a cost passed through, and a percentage of
     * it would be a percentage of the courier, not of the sale.
     *
     * Each row is counted the way Model/LineItems counts an order line, so the
     * basis is the sum of the item lines on the customer's receipt. A bundle
     * priced from its parts carries its amounts on its children; everything
     * else carries them on the row the customer sees.
     *
     * @param iterable<AbstractItem> $items
     * @return array{0: float, 1: float}
     */
    public function basis(iterable $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $child) {
                    $rows[] = $child;
                }
            } else {
                $rows[] = $item;
            }
        }

        $basis = 0.0;
        $baseBasis = 0.0;
        foreach ($rows as $row) {
            $basis += (float) $row->getRowTotal()
                + (float) $row->getTaxAmount()
                + (float) $row->getDiscountTaxCompensationAmount()
                - abs((float) $row->getDiscountAmount());
            $baseBasis += (float) $row->getBaseRowTotal()
                + (float) $row->getBaseTaxAmount()
                + (float) $row->getBaseDiscountTaxCompensationAmount()
                - abs((float) $row->getBaseDiscountAmount());
        }

        return [round($basis, 4), round($baseBasis, 4)];
    }
}
