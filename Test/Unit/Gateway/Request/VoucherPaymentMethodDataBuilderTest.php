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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Request;

use Buckaroo\Magento2\Gateway\Request\VoucherPaymentMethodDataBuilder;
use PHPUnit\Framework\TestCase;

class VoucherPaymentMethodDataBuilderTest extends TestCase
{
    /**
     * @var VoucherPaymentMethodDataBuilder
     */
    private $voucherPaymentMethodDataBuilder;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->voucherPaymentMethodDataBuilder = new VoucherPaymentMethodDataBuilder();
    }

    /**
     * Test that the build method returns the correct payment method
     */
    public function testBuild(): void
    {
        $buildSubject = []; // Empty array since this builder doesn't use build subject data
        
        $result = $this->voucherPaymentMethodDataBuilder->build($buildSubject);
        
        $expectedResult = [
            'payment_method' => 'buckaroovoucher'
        ];
        
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test that the payment method is always consistent
     */
    public function testBuildConsistency(): void
    {
        $buildSubject1 = ['payment' => 'dummy1'];
        $buildSubject2 = ['payment' => 'dummy2', 'order' => 'dummy3'];
        
        $result1 = $this->voucherPaymentMethodDataBuilder->build($buildSubject1);
        $result2 = $this->voucherPaymentMethodDataBuilder->build($buildSubject2);
        
        $this->assertEquals($result1, $result2);
        $this->assertEquals('buckaroovoucher', $result1['payment_method']);
        $this->assertEquals('buckaroovoucher', $result2['payment_method']);
    }
}
