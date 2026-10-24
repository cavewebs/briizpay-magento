<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Observer;

use BriizPay\PayByBank\Model\Api\ApiException;
use BriizPay\PayByBank\Model\Api\Client;
use BriizPay\PayByBank\Observer\ReportCreditMemo;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Reporting a store's credit memo to BriizPay.
 *
 * The rules worth pinning are about what is NOT sent: a card order's memo, a
 * memo on an order we never took, and the same memo twice. The last matters
 * most, because the event can fire again on a later save and a second refund
 * against a customer's payment is real money.
 *
 * @covers \BriizPay\PayByBank\Observer\ReportCreditMemo
 */
#[CoversClass(ReportCreditMemo::class)]
class ReportCreditMemoTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    /** @var array<int, string> */
    private array $comments = [];

    /** @param array<string, mixed> $response */
    private function client(
        ?ApiException $throws = null,
        array $response = ['id' => 'rf_1', 'status' => 'PENDING']
    ): Client {
        $client = $this->createStub(Client::class);
        $client->method('createRefund')->willReturnCallback(
            function (array $body) use ($throws, $response): array {
                if ($throws) {
                    throw $throws;
                }
                $this->sent[] = $body;
                return $response;
            }
        );
        return $client;
    }

    /**
     * A real Observer carrying a real Event, because the observer's signature
     * takes one.
     *
     * The order and the credit memo are plain DataObjects rather than real
     * Order and Creditmemo instances. Those take a Context and half of
     * Magento to construct, and the observer never asks what type they are:
     * it reads them through the same magic getters a DataObject provides.
     */
    private function observerFor(array $over = []): Observer
    {
        $settings = array_merge([
            'method' => 'briizpay',
            'requestId' => 'pr_1',
            'grandTotal' => 19.95,
            'note' => 'Returned',
            'incrementId' => '000000042',
            'reported' => null,
        ], $over);

        $comments = &$this->comments;

        $paymentData = ['method' => $settings['method'], 'requestId' => $settings['requestId']];
        $payment = new class ($paymentData) extends DataObject {
            public function getMethod(): string
            {
                return (string) $this->getData('method');
            }

            public function getAdditionalInformation($key = null)
            {
                return $this->getData('requestId');
            }
        };

        $orderData = ['payment' => $payment, 'store_id' => 1, 'increment_id' => '000000001'];
        $order = new class ($orderData, $comments) extends DataObject {
            /** @param array<int, string> $comments */
            public function __construct(array $data, private array &$comments)
            {
                parent::__construct($data);
            }

            public function getPayment()
            {
                return $this->getData('payment');
            }

            public function getStoreId()
            {
                return $this->getData('store_id');
            }

            public function getIncrementId()
            {
                return $this->getData('increment_id');
            }

            public function addCommentToStatusHistory($comment, $status = false, $isVisibleOnFront = false)
            {
                $this->comments[] = (string) $comment;
                return $this;
            }
        };

        $memoData = [
            'order' => $order,
            'grand_total' => $settings['grandTotal'],
            'customer_note' => $settings['note'],
            'increment_id' => $settings['incrementId'],
        ];
        if ($settings['reported'] !== null) {
            $memoData[ReportCreditMemo::REPORTED_FLAG] = $settings['reported'];
        }

        $creditmemo = new class ($memoData) extends DataObject {
            public function getOrder()
            {
                return $this->getData('order');
            }

            public function getGrandTotal()
            {
                return $this->getData('grand_total');
            }

            public function getCustomerNote()
            {
                return $this->getData('customer_note');
            }

            public function getIncrementId()
            {
                return $this->getData('increment_id');
            }

            public function getId()
            {
                return 7;
            }
        };

        return new Observer(['event' => new Event(['creditmemo' => $creditmemo])]);
    }

    protected function setUp(): void
    {
        $this->sent = [];
        $this->comments = [];
    }

    public function testReportsTheRefundAgainstThePaymentItBelongsTo(): void
    {
        $observer = new ReportCreditMemo($this->client(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observerFor());

        self::assertCount(1, $this->sent);
        self::assertSame('pr_1', $this->sent[0]['paymentRequestId']);
        self::assertSame(1995, $this->sent[0]['amountMinor'], 'in pence');
        self::assertSame('Returned', $this->sent[0]['reason']);
    }

    /** What makes a repeat safe at the other end. */
    public function testSendsTheStoresOwnIdForTheMemo(): void
    {
        $observer = new ReportCreditMemo($this->client(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observerFor());

        self::assertSame('magento_creditmemo_000000042', $this->sent[0]['externalReference']);
    }

    public function testIgnoresACreditMemoOnAnotherPaymentMethod(): void
    {
        $observer = new ReportCreditMemo($this->client(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observerFor(['method' => 'checkmo']));

        self::assertSame([], $this->sent);
    }

    public function testIgnoresAnOrderWithNoBriizpayPayment(): void
    {
        $observer = new ReportCreditMemo($this->client(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observerFor(['requestId' => '']));

        self::assertSame([], $this->sent);
    }

    /** The event can fire again on a later save, and a second refund is real money. */
    public function testReportsTheSameMemoOnlyOnce(): void
    {
        $observer = new ReportCreditMemo($this->client(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observerFor(['reported' => true]));

        self::assertSame([], $this->sent);
    }

    public function testIgnoresAMemoForNothing(): void
    {
        $observer = new ReportCreditMemo($this->client(), $this->createStub(LoggerInterface::class));
        $observer->execute($this->observerFor(['grandTotal' => 0.0]));

        self::assertSame([], $this->sent);
    }

    /**
     * The credit memo exists in Magento whatever happens here, so a refusal
     * cannot throw. The merchant is told instead, because the two sides now
     * disagree and only a person can put that right.
     */
    public function testLeavesACommentWhenBriizpayRefuses(): void
    {
        $observer = new ReportCreditMemo(
            $this->client(new ApiException(new Phrase('This payment has been fully refunded'))),
            $this->createStub(LoggerInterface::class)
        );

        $observer->execute($this->observerFor());

        self::assertStringContainsString('could not record this refund', implode(' ', $this->comments));
    }

    /**
     * On the Open Banking rail the money has not moved yet. The comment
     * repeats what BriizPay said rather than inventing a happier version.
     */
    public function testRepeatsBriizpaysOwnAnswerToTheMerchant(): void
    {
        $client = $this->client(null, [
            'id' => 'rf_1',
            'status' => 'PENDING',
            'msg' => 'Send the refund from your bank, then mark it complete in BriizPay.',
        ]);
        $observer = new ReportCreditMemo($client, $this->createStub(LoggerInterface::class));

        $observer->execute($this->observerFor());

        $said = implode(' ', $this->comments);
        self::assertStringContainsString('Send the refund from your bank', $said);
        self::assertDoesNotMatchRegularExpression('/\brefunded\b/i', $said, 'never claims the money has moved');
    }
}
