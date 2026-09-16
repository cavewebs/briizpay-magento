<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Controller\Webhook;

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\EventLog;
use BriizPay\PayByBank\Model\OrderCompleter;
use BriizPay\PayByBank\Model\PaymentStarter;
use BriizPay\PayByBank\Model\Signature;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

/**
 * Receiving payment_request.paid from BriizPay at /briizpay/webhook.
 *
 * This is what marks an order paid, not the customer coming back. It is public
 * because BriizPay has no account here: the signature is the authentication, and
 * it is checked before anything else, against the raw body byte for byte.
 *
 * Magento's form-key CSRF check does not apply to a server-to-server call, so it
 * is switched off for this action only, and replaced by the signature.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly Config $config,
        private readonly Signature $signature,
        private readonly EventLog $events,
        private readonly OrderFactory $orderFactory,
        private readonly OrderCompleter $completer,
        private readonly JsonFactory $jsonFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $secret = $this->config->getWebhookSecret();
        if ($secret === '') {
            $this->logger->error('[BriizPay] Webhook received but no signing secret is configured.');
            return $result->setHttpResponseCode(500)->setData(['error' => 'not configured']);
        }

        $body = (string) $this->request->getContent();
        $header = $this->request->getHeader(Signature::HEADER) ?: null;

        if (!$this->signature->verify($body, $header, $secret)) {
            $this->logger->warning('[BriizPay] Webhook rejected: bad signature.');
            return $result->setHttpResponseCode(401)->setData(['error' => 'invalid signature']);
        }

        try {
            $event = $this->json->unserialize($body);
        } catch (\Throwable) {
            $event = null;
        }
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            return $result->setHttpResponseCode(400)->setData(['error' => 'malformed']);
        }

        $eventId = (string) $event['id'];

        // At-least-once delivery: the same event arrives again after any failure.
        if (!$this->events->claim($eventId)) {
            return $result->setData(['received' => true, 'duplicate' => true]);
        }

        if ($event['type'] !== 'payment_request.paid') {
            // Accepted so BriizPay does not retry an event type this version
            // does not know yet.
            return $result->setData(['received' => true, 'ignored' => true]);
        }

        $data = isset($event['data']) && is_array($event['data']) ? $event['data'] : [];
        $order = $this->findOrder($data);

        if (!$order) {
            $this->logger->warning('[BriizPay] Webhook for an unknown order: ' . $body);
            // 200 on purpose: the order is not here, and retrying for hours will
            // not change that.
            return $result->setData(['received' => true, 'unknown_order' => true]);
        }

        try {
            $this->completer->complete($order, $data, 'webhook');
        } catch (\Throwable $e) {
            // Released so BriizPay's retry is applied rather than skipped as a
            // duplicate of an event that never took effect.
            $this->events->release($eventId);
            $this->logger->error('[BriizPay] Could not complete order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            return $result->setHttpResponseCode(500)->setData(['error' => 'could not complete order']);
        }

        return $result->setData(['received' => true]);
    }

    /**
     * externalReference is the order number we sent, and the payment request id
     * stored on the order must match, so an event cannot mark a different order
     * paid by naming its number.
     *
     * @param array<string, mixed> $data
     */
    private function findOrder(array $data): ?Order
    {
        $reference = isset($data['externalReference']) ? (string) $data['externalReference'] : '';
        $requestId = isset($data['paymentRequestId']) ? (string) $data['paymentRequestId'] : '';
        if ($reference === '' || $requestId === '') {
            return null;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($reference);
        if (!$order->getId() || $order->getPayment()?->getMethod() !== Config::METHOD_CODE) {
            return null;
        }

        $stored = (string) $order->getPayment()->getAdditionalInformation(PaymentStarter::INFO_REQUEST_ID);
        return hash_equals($stored, $requestId) ? $order : null;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
