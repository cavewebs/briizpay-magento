<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Config\Backend;

use BriizPay\PayByBank\Model\Discount;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * A discount amount has to be a number of zero or more, and a percentage
 * cannot be more than the whole order.
 *
 * Refused at save, with the reason, rather than saved and then quietly read as
 * something else every time a basket is totalled.
 */
class DiscountAmount extends Value
{
    public function beforeSave()
    {
        $value = trim((string) $this->getValue());
        if ($value === '' || !is_numeric($value) || (float) $value < 0) {
            throw new LocalizedException(__('The pay by bank discount must be a number of zero or more.'));
        }

        // The type is saved in the same form post; the saved value is only the
        // fallback when this field is saved on its own.
        $type = (string) ($this->getFieldsetDataValue('discount_type')
            ?? $this->_config->getValue('payment/briizpay/discount_type', $this->getScope() ?: 'default', $this->getScopeCode()));
        if ($type !== Discount::TYPE_FIXED && (float) $value > 100) {
            throw new LocalizedException(__('A percentage discount cannot be more than 100.'));
        }

        $this->setValue((string) (float) $value);
        return parent::beforeSave();
    }
}
