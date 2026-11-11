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
namespace Buckaroo\Magento2\Test\Unit\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Validator\AvailableBasedOnCustomerGroupValidator;
use Buckaroo\Magento2\Helper\Customer;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

/**
 * In admin order create the ambient store is not the quote's, so the customer group check gets
 * the quote's store.
 */
class AvailableBasedOnCustomerGroupValidatorTest extends TestCase
{
    public function testPassesTheQuotesStore(): void
    {
        $helper = $this->createMock(Customer::class);
        $helper->expects($this->once())->method('checkCustomerGroup')
            ->with('buckaroo_magento2_ideal', false, 6)
            ->willReturn(true);

        $resultFactory = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->disableOriginalConstructor()->onlyMethods(['create'])->getMock();
        $resultFactory->method('create')->willReturn($this->createMock(ResultInterface::class));

        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn('buckaroo_magento2_ideal');
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn('6');

        (new AvailableBasedOnCustomerGroupValidator($helper, $resultFactory))
            ->validate(['paymentMethodInstance' => $method, 'quote' => $quote]);
    }
}
