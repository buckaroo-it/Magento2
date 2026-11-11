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

namespace Buckaroo\Magento2\Test\Unit\Model\System\Message;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Idin\CheckoutSupport;
use Buckaroo\Magento2\Model\System\Message\IdinCheckoutStepMissing;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class IdinCheckoutStepMissingTest extends BaseTest
{
    /**
     * @param array<int, array{name: string, active: bool, idin: string, checkout: string|null}> $stores
     *
     * @return IdinCheckoutStepMissing
     */
    private function getMessage(array $stores): IdinCheckoutStepMissing
    {
        $storeMocks = [];
        foreach ($stores as $id => $data) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($id);
            $store->method('getName')->willReturn($data['name']);
            $store->method('getIsActive')->willReturn($data['active'] ? 1 : 0);
            $storeMocks[] = $store;
        }

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($storeMocks);

        $account = $this->createMock(Account::class);
        $account->method('getIdin')->willReturnCallback(static fn ($storeId) => $stores[$storeId]['idin']);

        $checkoutSupport = $this->createMock(CheckoutSupport::class);
        $checkoutSupport->method('getCheckoutWithoutIdinStep')
            ->willReturnCallback(static fn ($storeId) => $stores[$storeId]['checkout']);

        return new IdinCheckoutStepMissing($storeManager, $account, $checkoutSupport);
    }

    public function testNotDisplayedWhenEveryCheckoutHasAnIdinStep(): void
    {
        $message = $this->getMessage([
            1 => ['name' => 'Default Store View', 'active' => true, 'idin' => '1', 'checkout' => null],
        ]);

        $this->assertFalse($message->isDisplayed());
    }

    public function testNotDisplayedWhenIdinIsDisabled(): void
    {
        $message = $this->getMessage([
            1 => ['name' => 'Default Store View', 'active' => true, 'idin' => '0', 'checkout' => CheckoutSupport::HYVA_CHECKOUT],
        ]);

        $this->assertFalse($message->isDisplayed());
    }

    public function testNotDisplayedForInactiveStoreViews(): void
    {
        $message = $this->getMessage([
            1 => ['name' => 'Old Store View', 'active' => false, 'idin' => '1', 'checkout' => CheckoutSupport::HYVA_CHECKOUT],
        ]);

        $this->assertFalse($message->isDisplayed());
    }

    public function testNamesHyvaCheckoutStoreViewsAndTheUpdate(): void
    {
        $message = $this->getMessage([
            1 => ['name' => 'NL Store View', 'active' => true, 'idin' => '1', 'checkout' => CheckoutSupport::HYVA_CHECKOUT],
            2 => ['name' => 'BE Store View', 'active' => true, 'idin' => '1', 'checkout' => null],
        ]);

        $this->assertTrue($message->isDisplayed());
        $text = (string)$message->getText();
        $this->assertStringContainsString('NL Store View (Hyvä Checkout)', $text);
        $this->assertStringNotContainsString('BE Store View', $text);
        $this->assertStringContainsString('update the Buckaroo Hyvä Checkout module to version 2.4.0 or higher', $text);
        $this->assertStringNotContainsString('Hyvä React Checkout', $text);
    }

    public function testNamesHyvaReactCheckoutStoreViews(): void
    {
        $message = $this->getMessage([
            1 => ['name' => 'NL Store View', 'active' => true, 'idin' => '1', 'checkout' => CheckoutSupport::HYVA_REACT_CHECKOUT],
        ]);

        $text = (string)$message->getText();
        $this->assertStringContainsString('NL Store View (Hyvä React Checkout)', $text);
        $this->assertStringContainsString('The Hyvä React Checkout has no iDIN step', $text);
        $this->assertStringNotContainsString('version 2.4.0', $text);
    }

    public function testKeepsStoreViewsWithTheSameName(): void
    {
        $message = $this->getMessage([
            1 => ['name' => 'Store View', 'active' => true, 'idin' => '1', 'checkout' => CheckoutSupport::HYVA_CHECKOUT],
            2 => ['name' => 'Store View', 'active' => true, 'idin' => '1', 'checkout' => CheckoutSupport::HYVA_REACT_CHECKOUT],
        ]);

        $text = (string)$message->getText();
        $this->assertStringContainsString('Store View (Hyvä Checkout), Store View (Hyvä React Checkout)', $text);
    }

    public function testIsAMajorMessageWithAFixedIdentity(): void
    {
        $message = $this->getMessage([]);

        $this->assertSame(MessageInterface::SEVERITY_MAJOR, $message->getSeverity());
        $this->assertSame('buckaroo_idin_checkout_step_missing', $message->getIdentity());
    }
}
