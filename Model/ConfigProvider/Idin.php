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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\ResourceModel\Address;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Idin config provider
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Idin extends AbstractConfigProvider
{
    public const XPATH_ACCOUNT_IDIN = 'buckaroo_magento2/account/idin';

    /**
     * @var array|array[]
     */
    protected $issuers = [
        [
            'name' => 'ABN AMRO',
            'code' => 'ABNANL2A',
        ],
        [
            'name' => 'ING',
            'code' => 'INGBNL2A',
        ],
        [
            'name' => 'Rabobank',
            'code' => 'RABONL2U',
        ],
        [
            'name' => 'SNS Bank',
            'code' => 'SNSBNL2A',
        ],
        [
            'name' => 'ASN Bank',
            'code' => 'ASNBNL21',
        ],
        [
            'name' => 'RegioBank',
            'code' => 'RBRBNL21',
        ],
        [
            'name' => 'Bunq Bank',
            'code' => 'BUNQNL2A',
        ],
        [
            'name' => 'Triodos Bank',
            'code' => 'TRIONL2U',
        ],
    ];

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Account
     */
    private $configProviderAccount;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Address
     */
    private $addressResource;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param Account $configProviderAccount
     * @param StoreManagerInterface $storeManager
     * @param CustomerSession $customerSession
     * @param ProductFactory $productFactory
     * @param CheckoutSession $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param AddressFactory $addressFactory
     * @param CustomerRepository $customerRepository
     * @param Address $addressResource
     * @param ProductRepositoryInterface $productRepository
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Account $configProviderAccount,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        ProductFactory $productFactory,
        CheckoutSession $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        AddressFactory $addressFactory,
        CustomerRepository $customerRepository,
        \Magento\Customer\Model\ResourceModel\Address $addressResource,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($scopeConfig);
        $this->storeManager = $storeManager;
        $this->configProviderAccount = $configProviderAccount;
        $this->customerSession = $customerSession;
        $this->productFactory = $productFactory;
        $this->checkoutSession = $checkoutSession;
        $this->addressFactory = $addressFactory;
        $this->customerRepository = $customerRepository;
        $this->addressResource = $addressResource;
        $this->productRepository = $productRepository;
    }

    /**
     * Retrieve associated array of checkout configuration
     *
     * @param null|int|string $store
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     *
     * @return array
     */
    public function getConfig($store = null): array
    {
        $idin = $this->isIDINActive();
        $osc = (int)$this->scopeConfig->getValue(
            'onestepcheckout_iosc/general/enable',
            ScopeInterface::SCOPE_STORE
        );
        return [
            'buckarooIdin' => [
                'issuers'      => $this->getIssuers(),
                'active'       => $idin['active'],
                'verified'     => $idin['verified'],
                'isOscEnabled' => $osc
            ],
        ];
    }

    /**
     * Check if idin is active for this user and cart
     *
     * @throws NoSuchEntityException|LocalizedException
     *
     * @return array
     */
    protected function isIDINActive(): array
    {
        return $this->getIdinStatus(
            $this->checkoutSession->getQuote(),
            $this->getCustomer(
                $this->customerSession->getCustomerId()
            )
        );
    }

    /**
     * Get idin status for customer and Quote/Cart
     *
     * "active" is true when the cart needs an iDIN age verification that has not been done yet.
     *
     * @param Quote                  $quote
     * @param CustomerInterface|null $customer
     *
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getIdinStatus(Quote $quote, ?CustomerInterface $customer = null): array
    {
        $store = $quote->getStoreId() ?: null;

        if (!$this->isIdinEnabled($store) || !$this->checkCountry($customer)) {
            return ['active' => false, 'verified' => false];
        }
        $active = false;

        $verified = $this->isCustomerVerified($customer);
        if (!$verified) {
            $active = $this->isIdinActiveForQuote($quote);
        }

        return ['active' => $active, 'verified' => $verified];
    }

    /**
     * Get idin status for a quote, using the customer the quote belongs to
     *
     * @param Quote $quote
     *
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getQuoteIdinStatus(Quote $quote): array
    {
        $customerId = $quote->getCustomerIsGuest() ? null : $quote->getCustomerId();

        return $this->getIdinStatus($quote, $this->getCustomer($customerId));
    }

    /**
     * Check if an express button must be hidden because iDIN verification is still needed
     *
     * On a product page the product decides, on other pages (cart) the items in the cart.
     *
     * @param Product|null $product
     *
     * @return bool
     */
    public function isVerificationRequiredForExpress(?Product $product = null): bool
    {
        try {
            if (!$this->isIdinEnabled()) {
                return false;
            }

            $customer = $this->getCustomer($this->customerSession->getCustomerId());
            if (!$this->checkCountry($customer) || $this->isCustomerVerified($customer)) {
                return false;
            }

            if ($product !== null) {
                return $this->isProductRestricted($product);
            }

            return $this->isIdinActiveForQuote($this->checkoutSession->getQuote());
        } catch (\Exception $e) {
            // When the requirement cannot be determined, keep the express button hidden
            return true;
        }
    }

    /**
     * Enable idin only for netherland
     *
     * @param CustomerInterface|null $customer
     *
     * @return bool
     */
    protected function checkCountry(?CustomerInterface $customer): bool
    {
        if ($customer === null) {
            return true;
        }

        if ($customer->getDefaultBilling()) {
            $billingAddress = $this->addressFactory->create();
            $this->addressResource->load($billingAddress, $customer->getDefaultBilling());
            if ($billingAddress->getCountryId()) {
                return strtolower($billingAddress->getCountryId()) == 'nl';
            }
        }

        return true;
    }

    /**
     * Check if idin is enabled
     *
     * @param mixed $store
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    protected function isIdinEnabled($store = null): bool
    {
        $storeToUse = $store ?: $this->storeManager->getStore();
        return $this->configProviderAccount->getIdin($storeToUse) != 0;
    }

    /**
     * Check if customer is verified
     *
     * @param CustomerInterface|null $customer
     *
     * @return bool
     */
    protected function isCustomerVerified(?CustomerInterface $customer = null): bool
    {
        if ($customer === null) {
            return $this->checkoutSession->getCustomerIDINIsEighteenOrOlder() === true;
        }
        return $customer->getCustomAttribute('buckaroo_idin_iseighteenorolder') !== null &&
            $customer->getCustomAttribute('buckaroo_idin_iseighteenorolder')->getValue() == 1;
    }

    /**
     * Check if idin verification is required in cart/quote
     *
     * Every item counts (child items of configurable and bundle products included):
     * one restricted item is enough to require the verification.
     *
     * @param Quote $quote
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function isIdinActiveForQuote(Quote $quote): bool
    {
        $storeId = $quote->getStoreId() ?: null;
        $checked = [];

        foreach ($quote->getAllItems() as $item) {
            $productId = (int)$item->getProductId();
            if ($productId === 0 || isset($checked[$productId])) {
                continue;
            }
            $checked[$productId] = true;

            $product = $this->productRepository->getById($productId, false, $storeId);
            if ($this->isProductRestricted($product, $storeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a product requires idin verification for the configured idin mode
     *
     * @param Product|\Magento\Catalog\Api\Data\ProductInterface $product
     * @param mixed $store
     *
     * @throws NoSuchEntityException
     *
     * @return bool
     */
    public function isProductRestricted($product, $store = null): bool
    {
        $storeToUse = $store ?: $this->storeManager->getStore();

        switch ((int)$this->configProviderAccount->getIdinMode($storeToUse)) {
            case 1:
                $attribute = $product->getCustomAttribute('buckaroo_product_idin');
                return $attribute !== null && $attribute->getValue() == 1;
            case 2:
                return $this->checkCategories($product, $storeToUse);
            default:
                return true;
        }
    }

    /**
     * Check if idin is required in product categories
     *
     * @param Product|\Magento\Catalog\Api\Data\ProductInterface $product
     * @param mixed $store
     *
     * @return bool
     */
    protected function checkCategories($product, $store = null): bool
    {
        $idinCategories = array_filter(
            array_map('trim', explode(',', (string)$this->configProviderAccount->getIdinCategory($store)))
        );

        foreach ((array)$product->getCategoryIds() as $cat) {
            if (in_array((string)$cat, $idinCategories, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get customer by id
     *
     * @param mixed $customerId
     *
     * @return CustomerInterface|null
     */
    protected function getCustomer($customerId): ?CustomerInterface
    {
        if (empty($customerId)) {
            return null;
        }

        try {
            return $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException|LocalizedException $e) {
            return null;
        }
    }

    /**
     * Get list of issuers
     *
     * @throws NoSuchEntityException
     *
     * @return array
     */
    public function getIssuers(): array
    {
        $all = $this->issuers;
        if ($this->configProviderAccount->getIdin($this->storeManager->getStore()) == 1) {
            $all[] = ['name' => 'TEST BANK', 'code' => 'BANKNL2Y'];
        }
        return $all;
    }

    /**
     * Get active status for idin method
     *
     * @param mixed $store
     *
     * @throws NoSuchEntityException
     *
     * @return int
     *
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getActive($store = null): int
    {
        $storeToUse = $store ?: $this->storeManager->getStore();
        return (int)$this->configProviderAccount->getIdin($storeToUse);
    }
}
