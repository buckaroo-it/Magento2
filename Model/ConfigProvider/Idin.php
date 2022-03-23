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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;

/**
 * Idin config provider
 */
class Idin extends AbstractConfigProvider
{
    const XPATH_ACCOUNT_IDIN = 'buckaroo_magento2/account/idin';

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

    private $storeManager;

    private $configProviderAccount;

    private $customerSession;

    private $productFactory;

    protected $checkoutSession;

    protected $scopeConfig;

    protected $addressFactory;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        CustomerRepository $customerRepository
    ) {
        $this->storeManager = $storeManager;
        $this->configProviderAccount = $configProviderAccount;
        $this->customerSession = $customerSession;
        $this->productFactory = $productFactory;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->addressFactory = $addressFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Retrieve associated array of checkout configuration
     *
     * @param null $store
     *
     * @return array
     */
    public function getConfig($store = null)
    {
        $idin = $this->isIDINActive();
        $osc = (int) $this->scopeConfig->getValue(
            'onestepcheckout_iosc/general/enable',
            ScopeInterface::SCOPE_STORE
        );
        return [
            'buckarooIdin' => [
                'issuers' => $this->getIssuers(),
                'active' => $idin['active'],
                'verified' => $idin['verified'],
                'isOscEnabled' => $osc
            ],
        ];
    }
    /**
     * Get list of issuers
     *
     * @return array
     */
    public function getIssuers()
    {
        $all = $this->issuers;
        if ($this->configProviderAccount->getIdin($this->storeManager->getStore()) == 1) {
            array_push($all, ['name' => 'TEST BANK', 'code' => 'BANKNL2Y']);
        }
        return $all;
    }
    /**
     * Get idin status for customer and Quote/Cart
     *
     * @param Quote $quote
     * @param CustomerInterface $customer
     *
     * @return array
     */
    public function getIdinStatus(Quote $quote, CustomerInterface $customer = null)
    {
        if (
            !$this->checkCountry($customer) &&
            !$this->isIdinEnabled()
        ) {
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
     * Check if idin is active for this user and cart
     *
     * @return array
     */
    protected function isIDINActive()
    {
        return $this->getIdinStatus(
            $this->checkoutSession->getQuote(),
            $this->getCustomer(
                $this->customerSession->getCustomerId()
            )
        );
    }
    /**
     * Get customer by id
     *
     * @param mixed $customerId
     *
     * @return CustomerInterface|null
     */
    protected function getCustomer($customerId)
    {
        if (empty($customerId)) {
            return;
        }
        return $this->customerRepository->getById($customerId);
    }
    /**
     * Check if customer is verified
     *
     * @param CustomerInterface|null $customer
     *
     * @return boolean
     */
    protected function isCustomerVerified(CustomerInterface $customer = null)
    {
        if ($customer === null) {
            return $this->checkoutSession->getCustomerIDINIsEighteenOrOlder() === true;
        }
        return ($customer->getCustomAttribute('buckaroo_idin_iseighteenorolder') !== null &&
            $customer->getCustomAttribute('buckaroo_idin_iseighteenorolder')->getValue() == 1
        );
    }
    /**
     * Check if idin verification is required in cart/quote
     *
     * @param Quote $quote
     *
     * @return boolean
     */
    public function isIdinActiveForQuote(Quote $quote)
    {
        $active = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            $productId = $item->getProductId();
            $product = $this->productFactory->create()->load($productId);

            switch ($this->configProviderAccount->getIdinMode($this->storeManager->getStore())) {
                case 1:
                    $active = $product->getCustomAttribute('buckaroo_product_idin')->getValue() == 1;
                    break;
                case 2:
                    $active = $this->checkCategories($product);
                    break;
                default:
                    $active = true;
                    break;
            }
        }
        return $active;
    }

    /**
     * Check if idin is enabled
     *
     * @return boolean
     */
    protected function isIdinEnabled()
    {
        return $this->configProviderAccount->getIdin($this->storeManager->getStore()) != 0;
    }

    /**
     * Check if idin is required in product categories
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return boolean
     */
    protected function checkCategories($product)
    {
        foreach ($product->getCategoryIds() as $cat) {
            if (in_array($cat, explode(',', $this->configProviderAccount->getIdinCategory()))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Enable idin only for netherland
     *
     * @param CustomerInterface|null $customer
     *
     * @return boolean
     */
    protected function checkCountry($customer)
    {
        if ($customer === null) {
            return true;
        }

        if ($customer->getDefaultBilling()) {
            if ($billingAddress = $this->addressFactory->create()->load($customer->getDefaultBilling())) {
                if ($billingAddress->getCountryId()) {
                    return strtolower($billingAddress->getCountryId()) == 'nl';
                }
            }
        }
        return true;
    }
}
