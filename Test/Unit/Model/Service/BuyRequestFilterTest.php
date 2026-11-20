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

namespace Buckaroo\Magento2\Test\Unit\Model\Service;

use Buckaroo\Magento2\Model\Service\BuyRequestFilter;
use PHPUnit\Framework\TestCase;

class BuyRequestFilterTest extends TestCase
{
    public function testKeepsProductSelectionFields(): void
    {
        $formData = [
            'product'                      => '12',
            'qty'                          => '2',
            'super_attribute'              => ['93' => '56'],
            'super_group'                  => ['13' => '1'],
            'options'                      => ['4' => 'engraving'],
            'bundle_option'                => ['1' => '3'],
            'bundle_option_qty'            => ['1' => '1'],
            'links'                        => ['7'],
            'selected_configurable_option' => '15',
            'related_product'              => '',
            'item'                         => '12',
            'giftcard_amount'              => 'custom',
            'custom_giftcard_amount'       => '25',
            'giftcard_sender_name'         => 'Sender',
            'giftcard_sender_email'        => 'sender@example.com',
            'giftcard_recipient_name'      => 'Recipient',
            'giftcard_recipient_email'     => 'recipient@example.com',
            'giftcard_message'             => 'Enjoy',
        ];

        $this->assertSame($formData, (new BuyRequestFilter())->filter($formData));
    }

    public function testDropsFieldsOutsideTheAllowlist(): void
    {
        $formData = [
            'product'               => '12',
            'qty'                   => '1',
            'custom_price'          => '1',
            'original_custom_price' => '1',
            'form_key'              => 'abc',
            'uenc'                  => 'xyz',
        ];

        $this->assertSame(['product' => '12', 'qty' => '1'], (new BuyRequestFilter())->filter($formData));
    }

    public function testFromFormStringParsesAndFiltersTheSerializedForm(): void
    {
        $this->assertSame(
            ['product' => '12', 'qty' => '1', 'super_attribute' => ['93' => '56']],
            (new BuyRequestFilter())->fromFormString(
                'product=12&qty=1&super_attribute%5B93%5D=56&custom_price=1&form_key=abc'
            )
        );
    }

    public function testAdditionalAllowedKeysCanBeConfigured(): void
    {
        $filter = new BuyRequestFilter(['custom_option_field']);

        $this->assertSame(
            ['product' => '12', 'custom_option_field' => 'x'],
            $filter->filter(['product' => '12', 'custom_option_field' => 'x', 'custom_price' => '1'])
        );
    }
}
