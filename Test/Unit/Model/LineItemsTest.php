<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\LineItems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The lines sent with a payment, which become the customer's receipt.
 *
 * Two rules decide everything here. The lines must add up to exactly what is
 * charged, because a receipt that disagrees with the amount taken is worse
 * than no receipt. And a shop's own discounts, gift cards and store credit
 * have to appear as something a person recognises rather than being hidden
 * inside the price of a mug.
 *
 * @covers \BriizPay\PayByBank\Model\LineItems
 */
#[CoversClass(LineItems::class)]
class LineItemsTest extends TestCase
{
    private const MUGS = [
        'name' => 'Blue mug', 'quantity' => 2.0, 'gross' => 2500, 'taxRateBps' => 2000, 'sku' => 'MUG-1',
    ];
    private const SHIPPING = [
        'name' => 'Flat Rate - Fixed', 'quantity' => 1, 'gross' => 500, 'taxRateBps' => 0, 'sku' => '',
    ];

    /** @param array<int, array<string, mixed>> $lines */
    private static function total(array $lines): int
    {
        return array_sum(array_map(
            static fn (array $line): int => (int) round($line['quantity'] * $line['unitPriceMinor']),
            $lines
        ));
    }

    /** @param array<int, array<string, mixed>> $lines */
    private static function last(array $lines): string
    {
        $line = end($lines);
        return $line['name'] . '@' . $line['unitPriceMinor'];
    }

    public function testCarriesQuantityUnitPriceAndSku(): void
    {
        $lines = LineItems::build([self::MUGS, self::SHIPPING], 3000);

        self::assertNotNull($lines);
        self::assertSame(2.0, (float) $lines[0]['quantity']);
        self::assertSame(1250, $lines[0]['unitPriceMinor']);
        self::assertSame('MUG-1', $lines[0]['sku']);
    }

    public function testOmitsTheSkuKeyWhenThereIsNone(): void
    {
        $lines = LineItems::build([self::MUGS, self::SHIPPING], 3000);

        self::assertArrayNotHasKey('sku', $lines[1]);
    }

    /** Magento prices include tax, so the line has to say so or the receipt doubles it. */
    public function testLinesAreTaxInclusiveAtTheItemRate(): void
    {
        $lines = LineItems::build([self::MUGS, self::SHIPPING], 3000);

        self::assertSame(1, (int) $lines[0]['taxInclusive']);
        self::assertSame(2000, $lines[0]['taxRateBps']);
    }

    public function testShippingIsALineOfItsOwn(): void
    {
        $lines = LineItems::build([self::MUGS, self::SHIPPING], 3000);

        self::assertSame('Flat Rate - Fixed@500', self::last($lines));
    }

    /**
     * Three for a tenner does not divide into a whole number of pence, so the
     * row is charged once at its full price rather than three times at a
     * rounded third, which would not add up.
     */
    public function testARowThatWillNotDivideIsChargedOnce(): void
    {
        $lines = LineItems::build([['name' => 'Tea', 'quantity' => 3.0, 'gross' => 1000, 'taxRateBps' => 0]], 1000);

        self::assertSame('Tea × 3', $lines[0]['name']);
        self::assertSame(1, (int) $lines[0]['quantity']);
        self::assertSame(1000, $lines[0]['unitPriceMinor']);
    }

    public function testLinesThatAlreadyAddUpGetNoExtraLine(): void
    {
        self::assertCount(2, LineItems::build([self::MUGS, self::SHIPPING], 3000));
    }

    /**
     * The balancing rule, which is the whole point of this class. Whatever the
     * difference, the lines are made to equal the charge, and the name of the
     * balancing line says what kind of difference it was.
     *
     * @dataProvider balanceProvider
     */
    #[DataProvider('balanceProvider')]
    public function testBalancesTheLinesToTheAmountCharged(
        array $rows,
        int $totalMinor,
        string $expectedLastLine,
        string $case
    ): void {
        $lines = LineItems::build($rows, $totalMinor);

        self::assertSame($expectedLastLine, self::last($lines), $case);
        self::assertSame($totalMinor, self::total($lines), 'the lines add up to what is charged');
    }

    public static function balanceProvider(): array
    {
        return [
            'a penny out' => [[self::MUGS, self::SHIPPING], 3001, 'Rounding@1', 'a penny is rounding, and says so'],
            'a large shortfall' => [
                [self::MUGS, self::SHIPPING], 1000, 'Other discounts@-2000', 'a big gap is a discount',
            ],
            'a large excess' => [[self::MUGS, self::SHIPPING], 3250, 'Other charges@250', 'a surcharge is named too'],
        ];
    }

    /** A reduction the shop already named needs no invented line beside it. */
    public function testANamedReductionNeedsNoBalancingLine(): void
    {
        $credit = ['name' => 'Store credit', 'quantity' => 1, 'gross' => -1000, 'taxRateBps' => 0];
        $lines = LineItems::build([self::MUGS, self::SHIPPING, $credit], 2000);

        self::assertSame('Store credit@-1000', self::last($lines));
    }

    /**
     * @dataProvider nothingToSendProvider
     */
    #[DataProvider('nothingToSendProvider')]
    public function testSendsNoLinesWhenThereIsNothingToList(array $rows, int $totalMinor, string $case): void
    {
        self::assertNull(LineItems::build($rows, $totalMinor), $case);
    }

    public static function nothingToSendProvider(): array
    {
        return [
            'no rows' => [[], 500, 'nothing to list'],
            'nothing to charge' => [[self::MUGS], 0, 'a free order needs no receipt lines'],
        ];
    }

    /**
     * A hundred lines is the cap the payments API sets. A long order is
     * summarised rather than refused, and still has to add up.
     */
    public function testALongOrderIsSummarisedAndStillAddsUp(): void
    {
        $rows = array_fill(0, 150, ['name' => 'Pin', 'quantity' => 1, 'gross' => 2, 'taxRateBps' => 0]);
        $lines = LineItems::build($rows, 299);

        self::assertCount(100, $lines);
        self::assertSame('52 more items@104', $lines[98]['name'] . '@' . $lines[98]['unitPriceMinor']);
        self::assertSame(299, self::total($lines));
    }

    /** A product name is shop data and lands on a receipt, so markup comes out. */
    public function testStripsMarkupAndEntitiesFromNames(): void
    {
        $lines = LineItems::build(
            [['name' => '<b>Fish &amp; chips</b>', 'quantity' => 1, 'gross' => 850, 'taxRateBps' => 0]],
            850
        );

        self::assertSame('Fish & chips', $lines[0]['name']);
    }

    /** Magento gives shipping as a net amount and its tax, never a rate. */
    public function testWorksShippingVatBackFromItsTax(): void
    {
        self::assertSame(2000, LineItems::rateBps(600, 100));
    }

    public function testThePayByBankDiscountIsALineOfItsOwn(): void
    {
        $rows = LineItems::reductionRows(
            [Discount::LABEL => '-0.3000', 'Gift card' => null, 'Store credit' => '0.0000']
        );
        $lines = LineItems::build(array_merge([self::MUGS, self::SHIPPING], $rows), 2970);

        self::assertCount(1, $rows, 'an unset or zero reduction sends no line');
        self::assertSame('Pay by bank discount@-30', self::last($lines));
        self::assertCount(3, $lines, 'a named discount leaves nothing to balance');
        self::assertSame(2970, self::total($lines));
    }

    /** Magento stores some reductions positive, and every one of them comes off. */
    public function testAReductionStoredPositiveIsStillTakenOff(): void
    {
        self::assertSame(-500, LineItems::reductionRows(['Gift card' => 5])[0]['gross']);
    }
}
