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

namespace Buckaroo\Magento2\Test\Unit\Model\ConfigProvider;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Idin;
use Buckaroo\Magento2\Test\BaseTest;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\ResourceModel\Address as AddressResource;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IdinTest extends BaseTest
{
    /** @var Account|MockObject */
    private $account;

    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var CustomerSession|MockObject */
    private $customerSession;

    /** @var CustomerRepository|MockObject */
    private $customerRepository;

    /** @var ProductRepositoryInterface|MockObject */
    private $productRepository;

    /** @var Product[] */
    private $products = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createMock(Account::class);
        $this->account->method('getIdin')->willReturn(1);

        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote', '__call'])
            ->getMock();
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);

        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productRepository->method('getById')->willReturnCallback(function ($id) {
            return $this->products[$id];
        });
    }

    private function getIdin(): Idin
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        return new Idin(
            $this->account,
            $storeManager,
            $this->customerSession,
            $this->createMock(ProductFactory::class),
            $this->checkoutSession,
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(AddressFactory::class),
            $this->customerRepository,
            $this->createMock(AddressResource::class),
            $this->productRepository
        );
    }

    private function addProduct(int $id, ?int $idinFlag, array $categories = []): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getCustomAttribute')->willReturn(
            $idinFlag === null ? null : new AttributeValue(['attribute_code' => 'buckaroo_product_idin', 'value' => $idinFlag])
        );
        $product->method('getCategoryIds')->willReturn($categories);
        $this->products[$id] = $product;
    }

    private function getQuote(array $productIds, bool $guest = true, ?int $customerId = null): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllItems', 'getStoreId'])
            ->getMock();
        $quote->method('getAllItems')->willReturn(array_map(static function ($id) {
            return new DataObject(['product_id' => $id]);
        }, $productIds));
        $quote->method('getStoreId')->willReturn(1);
        $quote->setData('customer_is_guest', $guest ? 1 : 0);
        $quote->setData('customer_id', $customerId);

        return $quote;
    }

    public function testAnyRestrictedItemRequiresVerificationInProductMode(): void
    {
        $this->account->method('getIdinMode')->willReturn(1);
        $this->addProduct(1, 1);
        $this->addProduct(2, 0);

        // The restricted item is not the last one in the cart
        $this->assertTrue($this->getIdin()->isIdinActiveForQuote($this->getQuote([1, 2])));
    }

    public function testProductWithoutIdinAttributeIsNotRestricted(): void
    {
        $this->account->method('getIdinMode')->willReturn(1);
        $this->addProduct(1, null);
        $this->addProduct(2, 0);

        $this->assertFalse($this->getIdin()->isIdinActiveForQuote($this->getQuote([1, 2])));
    }

    public function testAnyItemInRestrictedCategoryRequiresVerification(): void
    {
        $this->account->method('getIdinMode')->willReturn(2);
        $this->account->method('getIdinCategory')->willReturn('5, 7');
        $this->addProduct(1, null, ['3', '7']);
        $this->addProduct(2, null, ['4']);

        $this->assertTrue($this->getIdin()->isIdinActiveForQuote($this->getQuote([1, 2])));
        $this->assertFalse($this->getIdin()->isIdinActiveForQuote($this->getQuote([2])));
    }

    public function testGlobalModeRequiresVerificationForAnyCart(): void
    {
        $this->account->method('getIdinMode')->willReturn(0);
        $this->addProduct(1, 0);

        $this->assertTrue($this->getIdin()->isIdinActiveForQuote($this->getQuote([1])));
        $this->assertFalse($this->getIdin()->isIdinActiveForQuote($this->getQuote([])));
    }

    public function testUnverifiedGuestQuoteIsActive(): void
    {
        $this->account->method('getIdinMode')->willReturn(0);
        $this->addProduct(1, 0);
        $this->checkoutSession->method('__call')->willReturn(null);

        $status = $this->getIdin()->getQuoteIdinStatus($this->getQuote([1]));

        $this->assertSame(['active' => true, 'verified' => false], $status);
    }

    public function testGuestVerifiedInSessionIsNotActive(): void
    {
        $this->account->method('getIdinMode')->willReturn(0);
        $this->addProduct(1, 0);
        $this->checkoutSession->method('__call')
            ->with('getCustomerIDINIsEighteenOrOlder')
            ->willReturn(true);

        $status = $this->getIdin()->getQuoteIdinStatus($this->getQuote([1]));

        $this->assertSame(['active' => false, 'verified' => true], $status);
    }

    public function testCustomerVerificationIsReadFromTheQuoteCustomer(): void
    {
        $this->account->method('getIdinMode')->willReturn(0);
        $this->addProduct(1, 0);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getDefaultBilling')->willReturn(null);
        $customer->method('getCustomAttribute')->willReturn(
            new AttributeValue(['attribute_code' => 'buckaroo_idin_iseighteenorolder', 'value' => 1])
        );
        $this->customerRepository->expects($this->once())->method('getById')->with(15)->willReturn($customer);

        $status = $this->getIdin()->getQuoteIdinStatus($this->getQuote([1], false, 15));

        $this->assertSame(['active' => false, 'verified' => true], $status);
    }

    public function testDisabledIdinIsNeverActive(): void
    {
        $this->account = $this->createMock(Account::class);
        $this->account->method('getIdin')->willReturn(0);
        $this->account->method('getIdinMode')->willReturn(0);
        $this->addProduct(1, 1);

        $status = $this->getIdin()->getQuoteIdinStatus($this->getQuote([1]));

        $this->assertFalse($status['active']);
    }

    public function testExpressButtonOnCartFollowsCartItems(): void
    {
        $this->account->method('getIdinMode')->willReturn(1);
        $this->addProduct(1, 0);
        $this->addProduct(2, 1);
        $this->checkoutSession->method('__call')->willReturn(null);
        $this->checkoutSession->method('getQuote')->willReturn($this->getQuote([1, 2]));

        $this->assertTrue($this->getIdin()->isVerificationRequiredForExpress(null));
        $this->assertFalse($this->getIdin()->isVerificationRequiredForExpress($this->products[1]));
        $this->assertTrue($this->getIdin()->isVerificationRequiredForExpress($this->products[2]));
    }

    public function testExpressButtonShownForVerifiedGuest(): void
    {
        $this->account->method('getIdinMode')->willReturn(0);
        $this->addProduct(1, 1);
        $this->checkoutSession->method('__call')->willReturn(true);

        $this->assertFalse($this->getIdin()->isVerificationRequiredForExpress($this->products[1]));
    }
}
