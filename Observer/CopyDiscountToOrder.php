<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Observer;

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\Discount;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

/**
 * Put the basket's pay by bank discount on the order, and never on an order
 * that is not paid by bank.
 *
 * Copied here rather than through etc/fieldset.xml: QuoteManagement builds
 * the order through OrderInterface, which drops any field the interface does
 * not declare, so a fieldset copy of a new column never reaches the order.
 *
 * The grand total was copied from the basket already, with the discount taken
 * off. Plugin/Quote/RecollectStaleDiscount has just collected the totals again
 * if the discount did not match the method, so a discount still here on an
 * order paid another way means that did not happen: submit() was reached some
 * way the plugin did not see. The order is refused rather than placed at a
 * price the store never offered for that method.
 */
class CopyDiscountToOrder implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $amount = (float) $address->getData(Discount::FIELD);
        $baseAmount = (float) $address->getData(Discount::BASE_FIELD);

        if ($amount != 0.0 && $order->getPayment()?->getMethod() !== Config::METHOD_CODE) {
            throw new LocalizedException(
                __('Your basket total has changed. Please review your order and place it again.')
            );
        }

        $order->setData(Discount::FIELD, $amount);
        $order->setData(Discount::BASE_FIELD, $baseAmount);
    }
}
