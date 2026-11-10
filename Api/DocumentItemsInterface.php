<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Api;

use Magento\Framework\DataObject;

/**
 * The items total of an order, invoice or credit memo, on the discount's terms.
 *
 * @api
 */
interface DocumentItemsInterface
{
    /**
     * @param iterable<DataObject> $items order items, or invoice or credit memo items
     * @return array{0: float, 1: float} in the order currency and the base currency
     */
    public function gross(iterable $items): array;
}
