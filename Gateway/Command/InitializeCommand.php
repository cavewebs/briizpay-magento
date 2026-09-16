<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Gateway\Command;

use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order;

/**
 * Place the order as Pending Payment, with nothing captured.
 *
 * The payment is created on BriizPay after the order exists (see
 * Controller/Checkout/Redirect), because the order number is the reference and
 * the order total is the amount. It is marked paid later, by the webhook or the
 * return page, never here.
 */
class InitializeCommand implements CommandInterface
{
    /** @param array<string, mixed> $commandSubject */
    public function execute(array $commandSubject)
    {
        /** @var \Magento\Framework\DataObject $stateObject */
        $stateObject = $commandSubject['stateObject'];
        $stateObject->setData('state', Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('status', Order::STATE_PENDING_PAYMENT);
        $stateObject->setData('is_notified', false);

        return null;
    }
}
