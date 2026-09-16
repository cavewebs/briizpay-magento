<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Controller\Checkout;

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\PaymentStarter;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Straight after Place order: create the BriizPay payment and send the customer
 * to choose their bank.
 *
 * The order is read from the checkout session, never from the request, so this
 * URL can only ever start a payment for the order this browser just placed.
 * If BriizPay cannot be reached the order is cancelled and the basket put back,
 * so the customer can choose another way to pay without re-entering anything.
 */
class Redirect implements HttpGetActionInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly PaymentStarter $starter,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messages,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId() || $order->getPayment()?->getMethod() !== Config::METHOD_CODE) {
            return $redirect->setPath('checkout/cart');
        }

        if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
            // Already paid or already dropped: nothing to start.
            return $redirect->setPath('checkout/onepage/success');
        }

        try {
            return $redirect->setUrl($this->starter->payUrlFor($order));
        } catch (LocalizedException $e) {
            $message = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error('[BriizPay] Could not start payment for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            $message = (string) __('BriizPay could not take this payment right now.');
        }

        if ($order->canCancel()) {
            // cancel(), not registerCancellation(): it fires order_cancel_after,
            // which is what retires a pay link if one was created.
            $order->cancel();
            $order->addCommentToStatusHistory((string) __('BriizPay payment could not be started: %1', $message));
            $order->save();
        }
        $this->checkoutSession->restoreQuote();
        $this->messages->addErrorMessage(__('%1 Your basket is still here, so you can try again or choose another way to pay.', $message));

        return $redirect->setPath('checkout/cart');
    }
}
