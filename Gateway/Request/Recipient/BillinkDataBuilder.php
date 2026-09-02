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

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Magento\Store\Model\ScopeInterface;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Config\Source\BillinkCustomerType;
use Buckaroo\Magento2\Service\Formatter\BirthDateFormatter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\AddressFactory;

class BillinkDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Data
     */
    public $helper;

    /**
     * @var AddressFactory
     */
    private $addressFactory;

    /**
     * @param Data                 $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param AddressFactory       $addressFactory
     * @param BirthDateFormatter   $birthDateFormatter
     * @param string               $addressType
     */
    public function __construct(
        Data $helper,
        ScopeConfigInterface $scopeConfig,
        AddressFactory $addressFactory,
        BirthDateFormatter $birthDateFormatter,
        string $addressType = 'billing'
    ) {
        parent::__construct($birthDateFormatter, $addressType);
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @inheritdoc
     */
    protected function buildData(): array
    {
        $category = $this->getCategory();

        $data = [
            'category'  => $category,
            'careOf'    => $this->getCareOf(),
            'title'     => $this->getGender(),
            'initials'  => $this->getInitials(),
            'firstName' => $this->getFirstname(),
            'lastName'  => $this->getLastName(),
        ];

        // Only include birthDate when Magento already has one (checkout no longer
        // collects it). Omitting it lets Billink One request DOB on the hosted page.
        $birthDate = $this->getBirthDate();
        if ($birthDate !== null) {
            $data['birthDate'] = $birthDate;
        }

        if ($category == 'B2B') {
            $data['chamberOfCommerce'] = $this->getChamberOfCommerce();
        }

        return $data;
    }

    /**
     * Returns the birthdate of the customer, or null when Magento has none.
     *
     * Billink checkout no longer collects DOB. When Magento already has a
     * customer date of birth (account / order), it is sent so Billink One
     * does not ask again. When absent, birthDate is omitted and Billink One
     * collects it on the hosted payment page.
     *
     * @return string|null
     */
    protected function getBirthDate(): ?string
    {
        return $this->birthDateFormatter->format(
            $this->getRawBirthDate(),
            $this->getFormatDate()
        );
    }

    /**
     * Check if company is empty
     *
     * @param string|null $company
     *
     * @return bool
     */
    private function isCompanyEmpty(?string $company = null): bool
    {
        if (null === $company) {
            return true;
        }

        return strlen(trim($company)) === 0;
    }

    /**
     * @inheritdoc
     */
    protected function getCategory(): string
    {
        $billingAddress = $this->getOrder()->getBillingAddress();
        $shippingAddress = $this->getOrder()->getShippingAddress();
        $storeId = $this->getOrder()->getStoreId();
        $customerTypeConfig = $this->getConfigData('customer_type', $storeId);
        $isPostNLPickup = $this->isPostNLPickupOrder();

        // Check company in both billing and shipping addresses
        $billingCompany = $billingAddress ? $billingAddress->getCompany() : '';
        $shippingCompany = $shippingAddress ? $shippingAddress->getCompany() : '';

        // For PostNL pickup orders ignore shipping company field as it contains the pickup location name
        if ($isPostNLPickup) {
            $hasCompany = !$this->isCompanyEmpty($billingCompany);
        } else {
            $hasCompany = !$this->isCompanyEmpty($billingCompany) || !$this->isCompanyEmpty($shippingCompany);
        }

        if ($customerTypeConfig === BillinkCustomerType::CUSTOMER_TYPE_B2C) {
            return 'B2C';
        }

        if ($hasCompany && ($customerTypeConfig === BillinkCustomerType::CUSTOMER_TYPE_B2B || $customerTypeConfig === BillinkCustomerType::CUSTOMER_TYPE_BOTH)) {
            return 'B2B';
        }

        return 'B2C';
    }

    /**
     * Check if the order uses PostNL pickup shipping method
     *
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function isPostNLPickupOrder(): bool
    {
        $order = $this->getOrder();
        $quoteId = $order->getQuoteId();

        if (!empty($quoteId)) {
            $quoteAddress = $this->addressFactory->create();
            $collection = $quoteAddress->getCollection();
            $collection->addFieldToFilter('quote_id', $quoteId);
            $collection->addFieldToFilter('address_type', 'pakjegemak');
            $pakjegemakAddress = $collection->setPageSize(1)->getFirstItem();

            if ($pakjegemakAddress && $pakjegemakAddress->getId()) {
                return true;
            }
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        if ($billingAddress && $shippingAddress) {
            $billingCompany = $billingAddress->getCompany();
            $shippingCompany = $shippingAddress->getCompany();

            if ($this->isCompanyEmpty($billingCompany) && !$this->isCompanyEmpty($shippingCompany)) {
                return true;
            }
        }

        $shippingMethod = $order->getShippingMethod();
        if (empty($shippingMethod)) {
            return false;
        }

        $shippingMethodLower = strtolower((string)$shippingMethod);

        return (strpos($shippingMethodLower, 'postnl') !== false || strpos($shippingMethodLower, 'post_nl') !== false)
            && (strpos($shippingMethodLower, 'pickup') !== false || strpos($shippingMethodLower, 'pakjegemak') !== false);
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string   $field
     * @param int|null $storeId
     *
     * @throws LocalizedException
     *
     * @return mixed
     */
    public function getConfigData(string $field, ?int $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getOrder()->getStoreId();
        }
        $path = 'payment/' . $this->getPayment()->getMethodInstance()->getCode() . '/' . $field;
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @inheritdoc
     */
    protected function getCareOf(): string
    {
        $company = $this->getAddress()->getCompany();

        if ($company !== null && strlen(trim($company)) > 0) {
            return $company;
        }

        return $this->getFirstname() . ' ' . $this->getLastName();
    }

    /**
     * @inheritdoc
     */
    protected function getGender(): string
    {
            return 'unknown';
    }
}
