<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Total;

use Magento\Framework\DataObject;

/**
 * The items total of an order, invoice or credit memo, on the discount's terms.
 *
 * After discounts, with tax, without shipping: the same basis the discount was
 * worked out on at checkout, so the part of the order an invoice or credit
 * memo covers is measured the way the discount was.
 */
class DocumentItems
{
    /**
     * @param iterable<DataObject> $items order items, or invoice or credit memo items
     * @return array{0: float, 1: float} in the order currency and the base currency
     */
    public static function gross(iterable $items): array
    {
        $gross = 0.0;
        $base = 0.0;
        foreach ($items as $item) {
            // A parent or child that carries no amounts of its own, the same
            // rows Magento skips when it shares a discount across an invoice.
            $orderItem = $item->getOrderItem() ?: $item;
            if ($orderItem->isDummy()) {
                continue;
            }
            $gross += (float) $item->getRowTotal()
                + (float) $item->getTaxAmount()
                + (float) $item->getDiscountTaxCompensationAmount()
                - abs((float) $item->getDiscountAmount());
            $base += (float) $item->getBaseRowTotal()
                + (float) $item->getBaseTaxAmount()
                + (float) $item->getBaseDiscountTaxCompensationAmount()
                - abs((float) $item->getBaseDiscountAmount());
        }
        return [$gross, $base];
    }
}
