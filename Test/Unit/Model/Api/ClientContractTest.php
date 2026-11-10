<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model\Api;

use BriizPay\PayByBank\Api\ClientInterface;
use BriizPay\PayByBank\Api\DiscountCalculatorInterface;
use BriizPay\PayByBank\Api\DiscountInterface;
use BriizPay\PayByBank\Api\DocumentItemsInterface;
use BriizPay\PayByBank\Api\LineItemsInterface;
use BriizPay\PayByBank\Api\MoneyInterface;
use BriizPay\PayByBank\Model\Api\Client;
use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\DiscountCalculator;
use BriizPay\PayByBank\Model\LineItems;
use BriizPay\PayByBank\Model\Money;
use BriizPay\PayByBank\Model\Total\DocumentItems;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The services Magento injects are the interfaces, not the classes.
 *
 * A later swap of Client for the standalone PHP SDK is a preference change
 * in di.xml. These assertions fail if a helper is added without a contract.
 */
class ClientContractTest extends TestCase
{
    /**
     * @param class-string $implementation
     * @param class-string $contract
     * @dataProvider contractProvider
     */
    #[DataProvider('contractProvider')]
    public function testEachHelperImplementsItsContract(string $implementation, string $contract): void
    {
        self::assertTrue(
            is_subclass_of($implementation, $contract),
            $implementation . ' must implement ' . $contract
        );
    }

    public static function contractProvider(): array
    {
        return [
            'client' => [Client::class, ClientInterface::class],
            'money' => [Money::class, MoneyInterface::class],
            'discount' => [Discount::class, DiscountInterface::class],
            'line items' => [LineItems::class, LineItemsInterface::class],
            'document items' => [DocumentItems::class, DocumentItemsInterface::class],
            'discount calculator' => [DiscountCalculator::class, DiscountCalculatorInterface::class],
        ];
    }
}
