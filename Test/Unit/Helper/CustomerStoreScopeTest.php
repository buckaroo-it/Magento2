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
namespace Buckaroo\Magento2\Test\Unit\Helper;

use Buckaroo\Magento2\Helper\Customer;
use Buckaroo\Magento2\Model\Config\Source\Business;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use PHPUnit\Framework\TestCase;

/**
 * The allowed customer groups and the B2B switches are store-view settings. In admin order create
 * the ambient store is not the quote's, so availability is checked against the quote's store.
 */
class CustomerStoreScopeTest extends TestCase
{
    public function testEveryCustomerGroupSettingIsReadInTheGivenStore(): void
    {
        $afterpay = $this->createMock(Afterpay::class);
        $afterpay->expects($this->once())->method('getBusiness')->with(4)->willReturn(Business::BUSINESS_B2B);
        $afterpay->expects($this->once())->method('getSpecificCustomerGroupB2B')->with(4)
            ->willReturn((string)Group::CUST_GROUP_ALL);
        $afterpay->expects($this->never())->method('getSpecificCustomerGroup');

        $factory = $this->createMock(Factory::class);
        $factory->method('get')->with('afterpay')->willReturn($afterpay);
        $checkPaymentType = $this->createMock(CheckPaymentType::class);
        $checkPaymentType->method('isBuckarooMethod')->willReturn(true);

        $helper = new Customer(
            $this->createMock(Context::class),
            $factory,
            $this->createMock(CustomerRepositoryInterface::class),
            $this->createMock(State::class),
            $this->createMock(CustomerSession::class),
            $checkPaymentType
        );

        $this->assertTrue($helper->checkCustomerGroup('buckaroo_magento2_afterpay', false, 4));
    }
}
