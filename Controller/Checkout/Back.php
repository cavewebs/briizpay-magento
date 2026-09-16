<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Controller\Checkout;

use BriizPay\PayByBank\Model\Api\Client;
use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\OrderCompleter;
use BriizPay\PayByBank\Model\PaymentStarter;
use BriizPay\PayByBank\Model\ReturnToken;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

/**
 * The customer is back from BriizPay. Ask BriizPay what actually happened.
 *
 * Arriving here proves only that a browser came back, and anyone can build this
 * URL, so nothing is decided from the visit itself. The status comes from the
 * API. Paid goes to the success page (and invoices the order if the webhook has
 * not already). Cancelled or expired cancels the order and puts the basket back.
 * Still pending, which is normal while a bank finishes confirming, goes to the
 * success page with a note: the webhook completes the order when it lands.
 */
class Back implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly OrderFactory $orderFactory,
        private readonly ReturnToken $returnToken,
        private readonly Client $client,
        private readonly OrderCompleter $completer,
        private readonly CheckoutSession $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messages,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $incrementId = (string) $this->request->getParam('order', '');
        $token = (string) $this->request->getParam('token', '');

        $order = $incrementId !== '' ? $this->orderFactory->create()->loadByIncrementId($incrementId) : null;
        if (!$order || !$order->getId()
            || $order->getPayment()?->getMethod() !== Config::METHOD_CODE
            || !$this->returnToken->matches($order, $token)
        ) {
            return $redirect->setPath('checkout/cart');
        }

        // The success page reads the last order from the session. Setting it
        // here means a customer whose session changed while at their bank still
        // lands on the right confirmation.
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
        $this->checkoutSession->setLastOrderStatus($order->getStatus());

        if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
            return $redirect->setPath('checkout/onepage/success');
        }

        $requestId = (string) $order->getPayment()->getAdditionalInformation(PaymentStarter::INFO_REQUEST_ID);
        $status = '';
        $data = [];
        if ($requestId !== '') {
            try {
                $data = $this->client->getPaymentRequest($requestId, (int) $order->getStoreId());
                $status = (string) ($data['status'] ?? '');
            } catch (\Throwable $e) {
                $this->logger->warning('[BriizPay] Status check failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            }
        }

        if ($status === 'COMPLETED') {
            $this->completer->complete($order, $data, 'return page');
            return $redirect->setPath('checkout/onepage/success');
        }

        if ($status === 'CANCELLED' || $status === 'EXPIRED') {
            if ($order->canCancel()) {
                $order->cancel();
                $order->addCommentToStatusHistory((string) __('BriizPay payment was not completed (%1).', strtolower($status)));
                $order->save();
            }
            $this->checkoutSession->restoreQuote();
            $this->messages->addErrorMessage(__('Your payment was not completed. Your basket is still here, so you can try again or choose another way to pay.'));
            return $redirect->setPath('checkout/cart');
        }

        $this->messages->addNoticeMessage(__('We are waiting for your bank to confirm the payment. Your order will update as soon as it does.'));
        return $redirect->setPath('checkout/onepage/success');
    }
}
