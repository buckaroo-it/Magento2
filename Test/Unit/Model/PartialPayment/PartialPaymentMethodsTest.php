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

namespace Buckaroo\Magento2\Test\Unit\Model\PartialPayment;

use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentMethods;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class PartialPaymentMethodsTest extends TestCase
{
    /** @var PartialPaymentMethods */
    private $methods;

    protected function setUp(): void
    {
        $collection = $this->createMock(GiftcardCollection::class);
        $collection->method('getItemByColumnValue')->willReturnMap([
            ['servicecode', 'vvvgiftcard', new DataObject(['label' => 'VVV Cadeaukaart', 'logo' => 'giftcards/vvv.png'])],
        ]);

        $this->methods = new PartialPaymentMethods($collection);
    }

    public function testVoucherIsAPartialPaymentWithoutAGiftcardRow(): void
    {
        $this->assertTrue($this->methods->isPartialPayment('buckaroovoucher'));
        $this->assertTrue($this->methods->isVoucher('buckaroovoucher'));
        $this->assertSame('Voucher', $this->methods->getLabel('buckaroovoucher'));
        $this->assertSame(PartialPaymentMethods::TYPE_VOUCHER, $this->methods->find('buckaroovoucher')['type']);
    }

    public function testGiftcardIsResolvedFromTheGiftcardTable(): void
    {
        $this->assertSame(
            [
                'code'  => 'vvvgiftcard',
                'label' => 'VVV Cadeaukaart',
                'logo'  => 'giftcards/vvv.png',
                'type'  => PartialPaymentMethods::TYPE_GIFTCARD,
            ],
            $this->methods->find('vvvgiftcard')
        );
    }

    public function testOtherPaymentMethodsAreNotPartialPayments(): void
    {
        $this->assertFalse($this->methods->isPartialPayment('ideal'));
        $this->assertFalse($this->methods->isPartialPayment(null));
        $this->assertFalse($this->methods->isPartialPayment(''));
        $this->assertNull($this->methods->getLabel('ideal'));
    }
}
