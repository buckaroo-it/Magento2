<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Giftcard\Request;

use Buckaroo\Magento2\Gateway\Http\SDKTransferFactory;
use Buckaroo\Magento2\Model\Giftcard\Request\Giftcard;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Only the giftcards the merchant allows are sent to Buckaroo.
 */
class GiftcardTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = Giftcard::class;

    #[DataProvider('cardProvider')]
    public function testOnlyAnAllowedGiftcardIsSent(string $cardId, bool $isSent): void
    {
        $client = $this->getFakeMock(ClientInterface::class)->getMock();
        $client->expects($isSent ? $this->once() : $this->never())
            ->method('placeRequest')
            ->willReturn(['object' => []]);

        $instance = $this->buildInstance('boekenbon,vvvgiftcard', $client);
        $instance->setCardId($cardId)->setCardNumber('123456')->setPin('1234');

        if (!$isSent) {
            $this->expectException(GiftcardException::class);
        }

        $instance->send();
    }

    public static function cardProvider(): array
    {
        return [
            'allowed giftcard'             => ['vvvgiftcard', true],
            'giftcard that is not allowed' => ['fashioncheque', false],
            'part of an allowed code'      => ['vvv', false],
        ];
    }

    private function buildInstance(string $allowed, $client): Giftcard
    {
        $scopeConfig = $this->getFakeMock(ScopeConfigInterface::class)->getMock();
        $scopeConfig->method('getValue')->willReturnMap([
            ['payment/buckaroo_magento2_giftcards/allowed_giftcards', 'store', 3, $allowed],
        ]);

        $transferFactory = $this->getFakeMock(SDKTransferFactory::class)->onlyMethods(['create'])->getMock();
        $transferFactory->method('create')->willReturn($this->getFakeMock(TransferInterface::class)->getMock());

        $quote = $this->getFakeMock(Quote::class)->onlyMethods(['getStoreId'])->getMock();
        $quote->method('getStoreId')->willReturn(3);

        $instance = $this->getFakeMock(Giftcard::class)
            ->onlyMethods(['getBody'])
            ->disableOriginalConstructor()
            ->getMock();
        $instance->method('getBody')->willReturn([]);
        $instance->setQuote($quote);

        $this->setGiftcardProperty($instance, 'scopeConfig', $scopeConfig);
        $this->setGiftcardProperty($instance, 'transferFactory', $transferFactory);
        $this->setGiftcardProperty($instance, 'clientInterface', $client);

        return $instance;
    }

    private function setGiftcardProperty(Giftcard $instance, string $name, $value): void
    {
        $property = new \ReflectionProperty(Giftcard::class, $name);
        $property->setValue($instance, $value);
    }
}
