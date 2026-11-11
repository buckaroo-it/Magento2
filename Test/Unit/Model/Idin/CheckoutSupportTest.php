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

namespace Buckaroo\Magento2\Test\Unit\Model\Idin;

use Buckaroo\Magento2\Model\Idin\CheckoutSupport;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class CheckoutSupportTest extends BaseTest
{
    private const STORE_ID = 2;

    /**
     * A class that exists, standing in for the iDIN step of the Buckaroo Hyvä Checkout module
     */
    private const EXISTING_STEP_CLASS = \stdClass::class;

    private const MISSING_STEP_CLASS = 'Buckaroo\HyvaCheckout\Magewire\Checkout\NoSuchIdinStep';

    private const HYVA_MODULES = ['Hyva_Checkout' => true, 'Buckaroo_HyvaCheckout' => true];

    private const HYVA_DESKTOP = ['hyva_themes_checkout/general/checkout' => 'default'];

    private const LUMA_DESKTOP = ['hyva_themes_checkout/general/checkout' => 'magento_luma'];

    /**
     * @param array<string, bool>  $modules
     * @param array<string, mixed> $config
     * @param string               $stepClass
     *
     * @return CheckoutSupport
     */
    private function getSupport(array $modules, array $config, string $stepClass): CheckoutSupport
    {
        $moduleManager = $this->createMock(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturnCallback(
            static fn (string $module): bool => $modules[$module] ?? false
        );

        $storeValue = static function (string $path, string $scope, $storeId) use ($config) {
            return $scope === ScopeInterface::SCOPE_STORE && $storeId === self::STORE_ID
                ? ($config[$path] ?? null)
                : null;
        };
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback($storeValue);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path, string $scope, $storeId): bool => !empty($storeValue($path, $scope, $storeId))
        );

        return new CheckoutSupport($scopeConfig, $moduleManager, $stepClass);
    }

    public static function storeProvider(): array
    {
        $hyvaMobileOnly = self::LUMA_DESKTOP + [
            'hyva_themes_checkout/general/mobile/enable'   => '1',
            'hyva_themes_checkout/general/mobile/checkout' => 'default',
        ];
        $reactEnabled = ['hyva_react_checkout/general/enable' => '1'];

        return [
            'hyva checkout, Buckaroo module without the iDIN step' => [
                self::HYVA_MODULES, self::HYVA_DESKTOP, self::MISSING_STEP_CLASS, CheckoutSupport::HYVA_CHECKOUT,
            ],
            'hyva checkout, Buckaroo module with the iDIN step' => [
                self::HYVA_MODULES, self::HYVA_DESKTOP, self::EXISTING_STEP_CLASS, null,
            ],
            'hyva checkout, Buckaroo module disabled' => [
                ['Hyva_Checkout' => true], self::HYVA_DESKTOP, self::EXISTING_STEP_CLASS, CheckoutSupport::HYVA_CHECKOUT,
            ],
            'hyva checkout on mobile only, Buckaroo module without the iDIN step' => [
                self::HYVA_MODULES, $hyvaMobileOnly, self::MISSING_STEP_CLASS, CheckoutSupport::HYVA_CHECKOUT,
            ],
            'magento checkout, mobile checkout disabled' => [
                self::HYVA_MODULES,
                self::LUMA_DESKTOP + ['hyva_themes_checkout/general/mobile/checkout' => 'default'],
                self::MISSING_STEP_CLASS,
                null,
            ],
            'magento checkout, mobile checkout set to the magento checkout' => [
                self::HYVA_MODULES,
                self::LUMA_DESKTOP + [
                    'hyva_themes_checkout/general/mobile/enable'   => '1',
                    'hyva_themes_checkout/general/mobile/checkout' => 'magento_luma',
                ],
                self::MISSING_STEP_CLASS,
                null,
            ],
            'hyva checkout setting left behind by a disabled hyva checkout module' => [
                [], self::HYVA_DESKTOP, self::MISSING_STEP_CLASS, null,
            ],
            'no hyva checkout installed' => [
                [], [], self::MISSING_STEP_CLASS, null,
            ],
            'hyva react checkout enabled' => [
                ['FriendsOfHyva_ReactCheckout' => true], $reactEnabled, self::MISSING_STEP_CLASS,
                CheckoutSupport::HYVA_REACT_CHECKOUT,
            ],
            'hyva react checkout enabled, module name before the move to Friends of Hyva' => [
                ['Hyva_ReactCheckout' => true], $reactEnabled, self::MISSING_STEP_CLASS,
                CheckoutSupport::HYVA_REACT_CHECKOUT,
            ],
            'hyva react checkout installed but not enabled for the store' => [
                ['FriendsOfHyva_ReactCheckout' => true], [], self::MISSING_STEP_CLASS, null,
            ],
            'hyva react checkout setting left behind by a disabled module' => [
                [], $reactEnabled, self::MISSING_STEP_CLASS, null,
            ],
        ];
    }

    /**
     * @param array<string, bool>  $modules
     * @param array<string, mixed> $config
     * @param string               $stepClass
     * @param string|null          $expected
     */
    #[DataProvider('storeProvider')]
    public function testGetCheckoutWithoutIdinStep(
        array $modules,
        array $config,
        string $stepClass,
        ?string $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->getSupport($modules, $config, $stepClass)->getCheckoutWithoutIdinStep(self::STORE_ID)
        );
    }

    public function testReadsTheSettingsOfTheGivenStore(): void
    {
        $support = $this->getSupport(self::HYVA_MODULES, self::HYVA_DESKTOP, self::MISSING_STEP_CLASS);

        $this->assertNull($support->getCheckoutWithoutIdinStep(self::STORE_ID + 1));
    }

    public function testDefaultStepClassIsTheIdinComponentOfTheBuckarooHyvaCheckoutModule(): void
    {
        $this->assertSame('Buckaroo\HyvaCheckout\Magewire\Checkout\Idin', CheckoutSupport::HYVA_IDIN_STEP_CLASS);
    }
}
