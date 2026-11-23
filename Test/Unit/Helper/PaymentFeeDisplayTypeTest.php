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

namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\PaymentFee;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\Config\Source\Display\Type as DisplayType;
use Buckaroo\Magento2\Model\ConfigProvider\Account as AccountConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee as BuckarooFeeConfigProvider;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodConfigProviderFactory;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection as GiftcardCollection;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fee total lines follow the fee display setting (tax/sales_display/buckaroo_fee),
 * not the fee tax class id.
 */
class PaymentFeeDisplayTypeTest extends TestCase
{
    public static function displayTypeProvider(): array
    {
        return [
            'excluding tax' => [DisplayType::DISPLAY_TYPE_EXCLUDING_TAX, [
                'buckaroo_fee' => ['Payment Fee (Excl. Tax)', 2.0, 3.0],
            ]],
            'including tax' => [DisplayType::DISPLAY_TYPE_INCLUDING_TAX, [
                'buckaroo_fee_incl' => ['Payment Fee (Incl. Tax)', 2.42, 3.63],
            ]],
            'both' => [DisplayType::DISPLAY_TYPE_BOTH, [
                'buckaroo_fee' => ['Payment Fee (Excl. Tax)', 2.0, 3.0],
                'buckaroo_fee_incl' => ['Payment Fee (Incl. Tax)', 2.42, 3.63],
            ]],
            'no setting falls back to excluding tax' => [0, [
                'buckaroo_fee' => ['Payment Fee (Excl. Tax)', 2.0, 3.0],
            ]],
        ];
    }

    #[DataProvider('displayTypeProvider')]
    public function testFeeLinesFollowTheDisplaySetting(int $displayType, array $expected): void
    {
        $feeConfig = $this->createMock(BuckarooFeeConfigProvider::class);
        $feeConfig->method('getSalesDisplayType')->willReturn($displayType);
        // The tax class id must no longer decide the display type
        $feeConfig->expects($this->never())->method('getBuckarooFeeTaxClass');

        $groupTransaction = $this->createMock(PaymentGroupTransaction::class);
        $groupTransaction->method('getAlreadyPaid')->willReturn(0.0);

        $helper = new PaymentFee(
            $this->createMock(Context::class),
            $this->createMock(AccountConfigProvider::class),
            $feeConfig,
            $this->createMock(MethodConfigProviderFactory::class),
            $groupTransaction,
            $this->createMock(GiftcardCollection::class)
        );

        $totals = $helper->getTotals(new DataObject([
            'buckaroo_fee'                 => 2.0,
            'buckaroo_fee_tax_amount'      => 0.42,
            'base_buckaroo_fee'            => 3.0,
            'buckaroo_fee_base_tax_amount' => 0.63,
        ]));

        $lines = [];
        foreach ($totals as $total) {
            $lines[$total['code']] = [(string)$total['label'], round((float)$total['value'], 2), round((float)$total['base_value'], 2)];
        }

        $this->assertSame($expected, $lines);
    }
}
