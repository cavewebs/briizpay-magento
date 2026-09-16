<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Config\Source;

use BriizPay\PayByBank\Model\Discount;
use Magento\Framework\Data\OptionSourceInterface;

/** The two kinds of pay by bank discount, worded as the WooCommerce plugin words them. */
class DiscountType implements OptionSourceInterface
{
    /** @return list<array{value: string, label: \Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => Discount::TYPE_PERCENT, 'label' => __('Percentage of the order')],
            ['value' => Discount::TYPE_FIXED, 'label' => __('Fixed amount')],
        ];
    }
}
