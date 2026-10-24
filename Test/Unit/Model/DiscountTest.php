<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Discount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The pay by bank discount.
 *
 * A saving the customer can see is the reason to choose their bank over a
 * card, so this arithmetic is the offer itself. These cases are the
 * WooCommerce plugin's tests/test-discount.php, case for case, so a merchant
 * running both gives their customers the same saving.
 *
 * @covers \BriizPay\PayByBank\Model\Discount
 */
#[CoversClass(Discount::class)]
class DiscountTest extends TestCase
{
    /**
     * @dataProvider computeProvider
     */
    #[DataProvider('computeProvider')]
    public function testComputesTheSaving(
        float|int|string $basis,
        string $type,
        float|int|string $amount,
        float $expected,
        string $case
    ): void {
        self::assertSame($expected, Discount::compute($basis, $type, $amount), $case);
    }

    public static function computeProvider(): array
    {
        return [
            'a percentage of a round basket' => [100.00, 'percent', 1, 1.00, 'one percent of £100 is £1'],
            'a percentage that lands between pennies' => [45.99, 'percent', 1, 0.46, 'rounded to the penny'],
            'a fraction of a percent' => [12.34, 'percent', 0.5, 0.06, 'half a percent still resolves'],
            'a fixed amount is pounds' => [100.00, 'fixed', 1, 1.00, 'fixed means £1, not 1%'],
            'strings from the config table' => ['60.00', 'percent', '2.5', 1.50, 'the admin stores strings'],
            'everything off' => [80.00, 'percent', 100, 80.00, 'one hundred percent is the basket'],
        ];
    }

    /**
     * The offer must never pay the customer. A fixed discount larger than the
     * basket, a negative amount or an empty basket all come to nothing taken
     * off rather than money added on.
     *
     * @dataProvider safetyProvider
     */
    #[DataProvider('safetyProvider')]
    public function testNeverGivesAwayMoreThanTheBasket(
        float|int|string $basis,
        string $type,
        float|int|string $amount,
        float $expected,
        string $case
    ): void {
        self::assertSame($expected, Discount::compute($basis, $type, $amount), $case);
    }

    public static function safetyProvider(): array
    {
        return [
            'a fixed amount above the basket' => [0.50, 'fixed', 5, 0.50, 'capped at the basket, never negative'],
            'nothing configured' => [100.00, 'percent', 0, 0.0, 'zero takes nothing off'],
            'a negative amount' => [100.00, 'percent', -3, 0.0, 'a negative discount is not a surcharge'],
            'an empty basket' => [0, 'percent', 1, 0.0, 'a percentage of nothing is nothing'],
            'a type nobody recognises' => [100.00, 'bogus', 1, 1.00, 'treated as a percentage, the safer of the two'],
        ];
    }

    /** A zero-decimal currency cannot express a saving under a whole unit. */
    public function testHonoursCurrencyPrecision(): void
    {
        self::assertSame(0.0, Discount::compute(45.99, 'percent', 1, 0));
    }

    /**
     * Splitting the discount across invoices and credit memos.
     *
     * A part-invoiced order takes its share, and the last document takes
     * whatever rounding left behind, so the documents always add back up to
     * the discount the customer was given.
     *
     * @dataProvider shareProvider
     */
    #[DataProvider('shareProvider')]
    public function testSharesAcrossDocuments(
        float $orderAmount,
        float $alreadyTaken,
        bool $isLast,
        float $part,
        float $whole,
        float $expected,
        string $case
    ): void {
        self::assertSame(
            $expected,
            Discount::share($orderAmount, $alreadyTaken, $isLast, $part, $whole),
            $case
        );
    }

    public static function shareProvider(): array
    {
        return [
            'one document for the whole order' => [1.20, 0.0, true, 120.00, 120.00, 1.20, 'takes it all'],
            'half the items' => [1.20, 0.0, false, 60.00, 120.00, 0.60, 'takes half'],
            'the last document mops up the rounding' => [1.00, 0.33, true, 40.00, 120.00, 0.67, 'takes what is left'],
            'more than is left' => [1.00, 0.90, false, 60.00, 120.00, 0.10, 'capped at the remainder'],
            'once it is all taken' => [1.00, 1.00, true, 60.00, 120.00, 0.0, 'nothing left to take'],
            'a document with no items' => [1.00, 0.0, false, 0.0, 120.00, 0.0, 'no items, no share'],
        ];
    }
}
