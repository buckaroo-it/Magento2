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
namespace Buckaroo\Magento2\Test\Unit\Service\Config;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Config\Source\PaymentMethods\Pos;
use Buckaroo\Magento2\Service\Config\AdminConfigScope;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * An admin request runs in the default store view whatever the scope switcher shows, so the
 * configuration page reads the scope from the switcher's request params.
 */
class AdminConfigScopeTest extends TestCase
{
    private function scope(array $params, ?StoreManagerInterface $storeManager = null): AdminConfigScope
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn ($name) => $params[$name] ?? null);

        return new AdminConfigScope($request, $storeManager ?? $this->createMock(StoreManagerInterface::class));
    }

    public function testStoreViewWins(): void
    {
        $scope = $this->scope(['store' => '3', 'website' => '1']);

        $this->assertSame(['stores', 3], $scope->getScope());
        $this->assertSame(3, $scope->getStoreId());
    }

    public function testWebsiteIsRepresentedByItsDefaultStoreView(): void
    {
        $defaultStore = $this->createMock(Store::class);
        $defaultStore->method('getId')->willReturn(5);
        $website = $this->getMockBuilder(\Magento\Store\Model\Website::class)
            ->disableOriginalConstructor()->onlyMethods(['getDefaultStore'])->getMock();
        $website->method('getDefaultStore')->willReturn($defaultStore);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->with(2)->willReturn($website);

        $scope = $this->scope(['website' => '2'], $storeManager);

        $this->assertSame(['websites', 2], $scope->getScope());
        $this->assertSame(5, $scope->getStoreId());
    }

    public function testDefaultScopeWhenNothingIsSelected(): void
    {
        $scope = $this->scope([]);

        $this->assertSame(['default', 0], $scope->getScope());
        $this->assertNull($scope->getStoreId());
    }

    public function testPosListsTheMethodsActiveAtTheEditedScope(): void
    {
        $adminScope = $this->createMock(AdminConfigScope::class);
        $adminScope->method('getScope')->willReturn(['websites', 2]);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            ['payment/buckaroo_magento2_ideal/active', 'websites', 2, '1'],
            ['payment/buckaroo_magento2_paypal/active', 'websites', 2, '0'],
        ]);
        $helper = $this->createMock(Data::class);
        $helper->method('getPaymentMethodsList')->willReturn([
            ['value' => 'ideal', 'label' => 'iDEAL'],
            ['value' => 'paypal', 'label' => 'PayPal'],
        ]);

        $options = (new Pos($scopeConfig, $helper, $adminScope))->toOptionArray();

        $this->assertSame(['', 'ideal'], array_column($options, 'value'));
    }
}
