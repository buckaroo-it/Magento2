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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Voucher;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The SDK adapter asks the config provider factory for the method behind a gateway service code
 * before every request. A service code with no matching provider makes every request for that
 * method fail, so each one that differs from its provider name must be mapped in etc/di.xml.
 */
class FactoryServiceCodeTest extends TestCase
{
    public function testVoucherServiceCodeResolvesToTheVoucherProvider(): void
    {
        $voucherProvider = $this->createMock(Voucher::class);

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->with(Voucher::class)->willReturn($voucherProvider);

        $factory = new Factory(
            $objectManager,
            $this->createMock(GiftcardCollection::class),
            ['voucher' => ['type' => 'voucher', 'model' => Voucher::class]],
            $this->getFactoryMapFromDiXml()
        );

        $this->assertSame($voucherProvider, $factory->get(Voucher::SERVICE_CODE));
    }

    /**
     * @return array<string, string>
     */
    private function getFactoryMapFromDiXml(): array
    {
        $xml = simplexml_load_file(__DIR__ . '/../../../../etc/di.xml');
        $this->assertNotFalse($xml, 'Could not parse etc/di.xml');

        $items = $xml->xpath(
            '//type[@name="Buckaroo\Magento2\Model\ConfigProvider\Factory"]'
            . '/arguments/argument[@name="mapPaymentMethods"]/item'
        );

        $map = [];
        foreach ($items as $item) {
            $map[(string)$item['name']] = (string)$item;
        }

        return $map;
    }
}
