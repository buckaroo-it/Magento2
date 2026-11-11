<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Test\Unit\Model\Service;

use Buckaroo\Magento2\Model\Service\BuyRequestFilter;
use PHPUnit\Framework\TestCase;

class BuyRequestFilterTest extends TestCase
{
    public function testKeepsProductSelectionFields(): void
    {
        $filter = new BuyRequestFilter();

        $result = $filter->fromFormString(
            'product=12&qty=2&super_attribute%5B93%5D=56&options%5B4%5D=red'
            . '&bundle_option%5B1%5D=3&bundle_option_qty%5B1%5D=2&links%5B%5D=7&super_group%5B5%5D=1'
            . '&selected_configurable_option=13&related_product=&item=12'
        );

        $this->assertSame('12', $result['product']);
        $this->assertSame('2', $result['qty']);
        $this->assertSame(['93' => '56'], $result['super_attribute']);
        $this->assertSame(['4' => 'red'], $result['options']);
        $this->assertSame(['1' => '3'], $result['bundle_option']);
        $this->assertSame(['1' => '2'], $result['bundle_option_qty']);
        $this->assertSame(['7'], $result['links']);
        $this->assertSame(['5' => '1'], $result['super_group']);
        $this->assertSame('13', $result['selected_configurable_option']);
    }

    public function testDropsFieldsOutsideTheProductSelection(): void
    {
        $filter = new BuyRequestFilter();

        $result = $filter->fromFormString(
            'product=12&qty=1&custom_price=1&original_custom_price=1&reset_count=1&id=99&form_key=abc'
        );

        $this->assertSame(['product' => '12', 'qty' => '1'], $result);
    }

    public function testAdditionalKeysCanBeAllowed(): void
    {
        $filter = new BuyRequestFilter(['engraving_text', 42]);

        $result = $filter->filter(['product' => '1', 'engraving_text' => 'Hi', 'custom_price' => '1']);

        $this->assertSame(['product' => '1', 'engraving_text' => 'Hi'], $result);
    }
}
