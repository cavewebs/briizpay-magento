<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pounds to pence.
 *
 * Magento keeps money as a float and BriizPay takes an integer of pence, so
 * this conversion sits on the path of every payment. A penny lost here is a
 * payment that does not match the order, and a float that rounds the wrong way
 * is the oldest bug in commerce.
 *
 * @covers \BriizPay\PayByBank\Model\Money
 */
#[CoversClass(Money::class)]
class MoneyTest extends TestCase
{
    /**
     * @dataProvider amountProvider
     */
    #[DataProvider('amountProvider')]
    public function testConvertsToPence(float|string $amount, int $expected, string $case): void
    {
        self::assertSame($expected, Money::toMinor($amount), $case);
    }

    public static function amountProvider(): array
    {
        return [
            'an ordinary price' => [19.99, 1999, '19.99 is 1999 pence'],
            'a string from the config table' => ['14.85', 1485, 'a string amount converts the same way'],
            // The canonical float example: 0.1 + 0.2 is 0.30000000000000004.
            'float arithmetic that does not land on a penny' => [
                0.1 + 0.2, 30, 'it rounds to 30 pence, not 30.000000004',
            ],
            'a half penny rounds up' => ['1234.565', 123457, 'half up, the way a till does'],
            'nothing is nothing' => [0.0, 0, 'zero stays zero'],
        ];
    }
}
