<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\PartialPayment\Instrument;

use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\Giftcard;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The request fields must match what the giftcard request builder sent before giftcards moved to
 * the shared partial payment service.
 */
class GiftcardTest extends TestCase
{
    #[DataProvider('cardFieldProvider')]
    public function testCardFieldsFollowTheAcquirerAndCard(
        string $cardId,
        ?string $acquirer,
        string $numberField,
        string $pinField
    ): void {
        $fields = $this->createGiftcard($cardId, ' 1234-5678 9 ', ' 0001 ', $acquirer)
            ->getRequestFields($this->createQuote());

        $this->assertSame('giftcards', $fields['payment_method']);
        $this->assertSame($cardId, $fields['name']);
        $this->assertSame('shopper@example.com', $fields['email']);
        $this->assertSame('123456789', $fields[$numberField]);
        $this->assertSame('0001', $fields[$pinField]);
    }

    public static function cardFieldProvider(): array
    {
        return [
            'intersolve card'                => ['vvvgiftcard', null, 'intersolveCardnumber', 'intersolvePIN'],
            'fashioncheque by card id'       => ['fashioncheque', null, 'fashionChequeCardNumber', 'fashionChequePin'],
            'tcs by acquirer'                => ['customgiftcard1', 'tcs', 'tcsCardnumber', 'tcsValidationCode'],
            'custom card without acquirer'   => ['customgiftcard2', null, 'cardNumber', 'pin'],
        ];
    }

    public function testACardTheStoreDoesNotAcceptIsRefused(): void
    {
        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage('This giftcard is not accepted in this store.');

        $this->createGiftcard('boekenbon', '1', '1')->validate($this->createQuote());
    }

    #[DataProvider('missingFieldProvider')]
    public function testMissingFieldsAreRefusedBeforeSending(string $cardId, string $number, string $pin, string $message): void
    {
        $this->expectException(PartialPaymentRejectedException::class);
        $this->expectExceptionMessage($message);

        $this->createGiftcard($cardId, $number, $pin)->validate($this->createQuote());
    }

    public static function missingFieldProvider(): array
    {
        return [
            'no card'   => ['', '1', '1', 'A card type is required'],
            'no number' => ['vvvgiftcard', ' ', '1', 'A card number is required'],
            'no pin'    => ['vvvgiftcard', '1', '', 'A card pin is required'],
        ];
    }

    public function testAnAcceptedCardPassesValidation(): void
    {
        $this->createGiftcard('vvvgiftcard', '1', '1')->validate($this->createQuote());

        $this->addToAssertionCount(1);
    }

    private function createGiftcard(string $cardId, string $number, string $pin, ?string $acquirer = null): Giftcard
    {
        $repository = $this->createMock(GiftcardRepositoryInterface::class);
        $repository->method('getByServiceCode')->willReturn(new DataObject(['acquirer' => $acquirer]));

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('vvvgiftcard, fashioncheque,customgiftcard1');

        return new Giftcard($repository, $scopeConfig, $cardId, $number, $pin);
    }

    private function createQuote(): Quote
    {
        $address = $this->createMock(Address::class);
        $address->method('getEmail')->willReturn('shopper@example.com');

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBillingAddress', 'getStoreId'])
            ->getMock();
        $quote->method('getBillingAddress')->willReturn($address);
        $quote->method('getStoreId')->willReturn(1);

        return $quote;
    }
}
