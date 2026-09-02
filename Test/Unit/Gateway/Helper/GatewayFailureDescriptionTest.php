<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Gateway\Helper;

use Buckaroo\Magento2\Gateway\Helper\GatewayFailureDescription;
use PHPUnit\Framework\TestCase;

class GatewayFailureDescriptionTest extends TestCase
{
    /**
     * @dataProvider uninformativeDescriptionProvider
     */
    public function testRecognisesDescriptionsWithoutAReason(string $description): void
    {
        $this->assertTrue(GatewayFailureDescription::isUninformative($description));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function uninformativeDescriptionProvider(): array
    {
        return [
            'empty string' => [''],
            'whitespace only' => ["  \t\n"],
            'single full stop' => ['.'],
            'dash placeholder' => [' - '],
            // The real BTI-1286 payload: Billink SubCode S996 with an empty
            // ErrorResponseMessage interpolated into Plaza's sentence template.
            'plaza template with empty reason' => ['An error occurred while processing the transaction: .'],
            'plaza template with blank tail' => ['Transaction failed:'],
        ];
    }

    /**
     * @dataProvider informativeDescriptionProvider
     */
    public function testKeepsDescriptionsThatCarryAReason(string $description): void
    {
        $this->assertFalse(GatewayFailureDescription::isUninformative($description));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function informativeDescriptionProvider(): array
    {
        return [
            'plain reason' => ['Insufficient funds'],
            'plaza template with a reason' => ['An error occurred while processing the transaction: card expired'],
            'reason with a numeric tail' => ['Transaction denied: 51'],
            'afterpay country code error' => ['deliveryCustomer.address.countryCode is invalid'],
        ];
    }

    public function testTreatsNullAsUninformative(): void
    {
        $this->assertTrue(GatewayFailureDescription::isUninformative(null));
    }
}
