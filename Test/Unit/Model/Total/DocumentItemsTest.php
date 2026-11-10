<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model\Total;

use BriizPay\PayByBank\Model\Total\DocumentItems;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The items total of an invoice or credit memo, on the discount's terms.
 *
 * After discounts, with tax, without shipping: the same basis checkout used,
 * so a partial invoice takes the same share of the saving the customer saw.
 *
 * @covers \BriizPay\PayByBank\Model\Total\DocumentItems
 */
#[CoversClass(DocumentItems::class)]
class DocumentItemsTest extends TestCase
{
    public function testSumsVisibleItemsAfterDiscountWithTax(): void
    {
        $item = new DataObject([
            'rowTotal' => 10.00,
            'taxAmount' => 2.00,
            'discountTaxCompensationAmount' => 0.0,
            'discountAmount' => 1.00,
            'baseRowTotal' => 10.00,
            'baseTaxAmount' => 2.00,
            'baseDiscountTaxCompensationAmount' => 0.0,
            'baseDiscountAmount' => 1.00,
        ]);

        self::assertSame([11.0, 11.0], (new DocumentItems())->gross([$item]));
    }

    public function testSkipsDummyRowsTheWayMagentoSharesADiscount(): void
    {
        $dummy = new class extends DataObject {
            public function isDummy(): bool
            {
                return true;
            }
        };

        self::assertSame([0.0, 0.0], (new DocumentItems())->gross([$dummy]));
    }

    public function testUsesTheDocumentRowWhenThereIsNoOrderItem(): void
    {
        $item = new DataObject([
            'rowTotal' => 5.00,
            'taxAmount' => 1.00,
            'discountTaxCompensationAmount' => 0.0,
            'discountAmount' => 0.0,
            'baseRowTotal' => 4.00,
            'baseTaxAmount' => 0.80,
            'baseDiscountTaxCompensationAmount' => 0.0,
            'baseDiscountAmount' => 0.0,
        ]);

        self::assertSame([6.0, 4.8], (new DocumentItems())->gross([$item]));
    }
}
