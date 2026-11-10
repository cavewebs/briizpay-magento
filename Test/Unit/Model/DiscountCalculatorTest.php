<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\DiscountCalculator;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Whether a basket is paid by bank, and what that earns it.
 *
 * isPaidByBank used to be static, which Magento cannot intercept. The rest of
 * the calculator was already a service; these cases pin the instance method
 * and that amountFor still refuses a basket that is not ours.
 *
 * @covers \BriizPay\PayByBank\Model\DiscountCalculator
 */
#[CoversClass(DiscountCalculator::class)]
class DiscountCalculatorTest extends TestCase
{
    private function calculator(
        bool $offered = true,
        string $type = Discount::TYPE_PERCENT,
        float $amount = 1.0
    ): DiscountCalculator {
        $config = $this->createStub(Config::class);
        $config->method('isDiscountOffered')->willReturn($offered);
        $config->method('getDiscountType')->willReturn($type);
        $config->method('getDiscountAmount')->willReturn($amount);

        return new DiscountCalculator($config, new Discount());
    }

    private function quote(string $method, int $storeId = 1): Quote
    {
        $payment = $this->createStub(Payment::class);
        $payment->method('getMethod')->willReturn($method);

        $quote = $this->createStub(Quote::class);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getStoreId')->willReturn($storeId);

        return $quote;
    }

    public function testAQuoteOnBriizpayIsPaidByBank(): void
    {
        self::assertTrue($this->calculator()->isPaidByBank($this->quote(Config::METHOD_CODE)));
    }

    public function testAQuoteOnAnotherMethodIsNotPaidByBank(): void
    {
        self::assertFalse($this->calculator()->isPaidByBank($this->quote('checkmo')));
    }

    public function testAmountForIsNothingWhenTheQuoteIsNotPaidByBank(): void
    {
        self::assertSame(
            [0.0, 0.0],
            $this->calculator()->amountFor($this->quote('checkmo'), [])
        );
    }

    public function testOfferForIsNothingWhenTheMerchantHasNotTurnedTheDiscountOn(): void
    {
        self::assertSame(
            [0.0, 0.0],
            $this->calculator(false)->offerFor([], 1)
        );
    }

    public function testOfferForUsesTheSameArithmeticAsTheDiscountService(): void
    {
        self::assertSame(
            [1.0, 1.0],
            $this->calculator(true, Discount::TYPE_PERCENT, 1.0)->offerFor([$this->item(100.00)], 1)
        );
    }

    public function testAmountForGivesTheOfferWhenTheQuoteIsPaidByBank(): void
    {
        self::assertSame(
            [0.5, 0.5],
            $this->calculator()->amountFor($this->quote(Config::METHOD_CODE), [$this->item(50.00)])
        );
    }

    private function item(float $rowTotal): AbstractItem
    {
        return new class ($rowTotal) extends AbstractItem {
            public function __construct(private readonly float $rowTotal)
            {
            }

            public function getRowTotal()
            {
                return $this->rowTotal;
            }

            public function getTaxAmount()
            {
                return 0.0;
            }

            public function getDiscountTaxCompensationAmount()
            {
                return 0.0;
            }

            public function getDiscountAmount()
            {
                return 0.0;
            }

            public function getBaseRowTotal()
            {
                return $this->rowTotal;
            }

            public function getBaseTaxAmount()
            {
                return 0.0;
            }

            public function getBaseDiscountTaxCompensationAmount()
            {
                return 0.0;
            }

            public function getBaseDiscountAmount()
            {
                return 0.0;
            }
        };
    }
}
