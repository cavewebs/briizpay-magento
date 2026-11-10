<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Money;
use BriizPay\PayByBank\Model\OrderCompleter;
use BriizPay\PayByBank\Model\PaymentStarter;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Transaction as DbTransaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Recording BriizPay's transaction id on the Magento payment.
 *
 * last_trans_id and a Sales > Transactions capture are what an admin searches.
 * A second notification for the same payment must not add a second row or comment.
 *
 * @covers \BriizPay\PayByBank\Model\OrderCompleter
 */
#[CoversClass(OrderCompleter::class)]
class OrderCompleterTest extends TestCase
{
    /** @var array<int, string> */
    private array $comments = [];

    private object $payment;

    private object $invoice;

    private int $saves = 0;

    private function completer(): OrderCompleter
    {
        $this->invoice = new class {
            public string $transactionId = '';

            public function setRequestedCaptureCase($case): static
            {
                return $this;
            }

            public function setTransactionId($id): static
            {
                $this->transactionId = (string) $id;
                return $this;
            }

            public function register(): static
            {
                return $this;
            }

            public function pay(): static
            {
                return $this;
            }
        };

        $invoices = $this->createStub(InvoiceService::class);
        $invoices->method('prepareInvoice')->willReturn($this->invoice);

        $db = $this->createStub(DbTransaction::class);
        $db->method('addObject')->willReturnSelf();
        $factory = $this->createStub(TransactionFactory::class);
        $factory->method('create')->willReturn($db);

        $this->saves = 0;
        $orders = $this->createStub(OrderRepositoryInterface::class);
        $orders->method('save')->willReturnCallback(function (): void {
            $this->saves++;
        });

        return new OrderCompleter(
            $invoices,
            $factory,
            $orders,
            $this->createStub(LoggerInterface::class),
            new Money()
        );
    }

    private function order(bool $alreadyPaid = false): object
    {
        $this->comments = [];
        $comments = &$this->comments;

        $this->payment = new class extends DataObject {
            /** @var array<int, array<string, mixed>> */
            public array $transactions = [];

            public function getLastTransId(): string
            {
                return (string) ($this->getData('lastTransId') ?? '');
            }

            public function setLastTransId($id): static
            {
                return $this->setData('lastTransId', $id);
            }

            public function setTransactionId($id): static
            {
                return $this->setData('transactionId', $id);
            }

            public function getTransactionId(): string
            {
                return (string) ($this->getData('transactionId') ?? '');
            }

            public function getAdditionalInformation($key = null)
            {
                return $this->getData($key);
            }

            public function setAdditionalInformation($key, $value = null): static
            {
                return $this->setData($key, $value);
            }

            public function addTransaction($type, $document = false, $failSafe = false)
            {
                $this->transactions[] = [
                    'type' => $type,
                    'txn_id' => $this->getTransactionId(),
                ];
                return $this;
            }
        };

        return new class ($this->payment, $comments, $alreadyPaid) extends Order {
            /**
             * @param array<int, string> $comments
             */
            public function __construct(
                private object $payment,
                private array &$comments,
                private bool $invoiced
            ) {
                parent::__construct([
                    'state' => $invoiced ? Order::STATE_PROCESSING : Order::STATE_PENDING_PAYMENT,
                    'grand_total' => 19.95,
                    'increment_id' => '000000001',
                ]);
            }

            public function hasInvoices(): bool
            {
                return $this->invoiced;
            }

            public function getState()
            {
                return $this->getData('state');
            }

            public function setState($state): static
            {
                return $this->setData('state', $state);
            }

            public function setStatus($status): static
            {
                return $this;
            }

            public function getPayment()
            {
                return $this->payment;
            }

            public function getGrandTotal()
            {
                return $this->getData('grand_total');
            }

            public function getIncrementId()
            {
                return $this->getData('increment_id');
            }

            public function canUnhold(): bool
            {
                return false;
            }

            public function getConfig(): object
            {
                return new class {
                    public function getStateDefaultStatus($state)
                    {
                        return $state;
                    }
                };
            }

            public function addCommentToStatusHistory($comment, $status = false, $isVisibleOnFront = false)
            {
                $this->comments[] = (string) $comment;
                return new class {
                    public function setIsCustomerNotified($value): static
                    {
                        return $this;
                    }
                };
            }
        };
    }

    public function testStoresAPresentIdOnThePaymentAndAsACapture(): void
    {
        $order = $this->order();
        $did = $this->completer()->complete(
            $order,
            ['amountMinor' => 1995, 'transactionId' => 'txn_abc'],
            'webhook'
        );

        self::assertTrue($did);
        self::assertSame('txn_abc', $this->payment->getLastTransId());
        self::assertSame('txn_abc', $this->payment->getAdditionalInformation(PaymentStarter::INFO_TRANSACTION_ID));
        self::assertSame('txn_abc', $this->invoice->transactionId);
        self::assertSame([['type' => 'capture', 'txn_id' => 'txn_abc']], $this->payment->transactions);
        self::assertContains('BriizPay transaction ID: txn_abc', $this->comments);
        self::assertContains('Payment confirmed by BriizPay (webhook).', $this->comments);
    }

    public function testANullIdStillMarksTheOrderPaidWithoutAGatewayTxn(): void
    {
        $order = $this->order();
        $did = $this->completer()->complete($order, ['amountMinor' => 1995], 'return page');

        self::assertTrue($did);
        self::assertSame('', $this->payment->getLastTransId());
        self::assertSame([], $this->payment->transactions);
        self::assertSame('', $this->invoice->transactionId);
        self::assertNotContains('BriizPay transaction ID: ', $this->comments);
        self::assertContains('Payment confirmed by BriizPay (return page).', $this->comments);
    }

    public function testADuplicateNotificationDoesNotAddASecondTransactionOrComment(): void
    {
        $completer = $this->completer();
        $order = $this->order();
        $payload = ['amountMinor' => 1995, 'transactionId' => 'txn_abc'];

        self::assertTrue($completer->complete($order, $payload, 'webhook'));
        self::assertFalse($completer->complete($order, $payload, 'return page'));

        self::assertCount(1, $this->payment->transactions);
        self::assertSame(1, substr_count(implode("\n", $this->comments), 'BriizPay transaction ID: txn_abc'));
        self::assertSame(0, $this->saves, 'the first save is the invoice transaction, not a second order save');
    }
}
