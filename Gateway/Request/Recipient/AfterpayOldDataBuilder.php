<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;
use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;
use Buckaroo\Resources\Constants\Gender;
use Buckaroo\Resources\Constants\RecipientCategory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class AfterpayOldDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * Business methods that will be used in afterpay.
     */
    const BUSINESS_METHOD_B2C = 1;
    const BUSINESS_METHOD_B2B = 2;

    protected ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig, string $addressType = 'billing')
    {
        parent::__construct($addressType);
        $this->scopeConfig = $scopeConfig;
    }

    protected function buildData(): array
    {
        $data = [
            'title' => $this->getFirstname(),
            'gender' => $this->getGender(),
            'initials' => $this->getInitials(),
            'lastName' => $this->getLastName(),
            'birthDate' => $this->getBirthDate(),
            'culture' => $this->getOrder()->getBillingAddress()->getCountryId()
        ];

        if ($this->getCategory() == RecipientCategory::COMPANY) {
            $data['chamberOfCommerce'] = $this->getPayment()->getAdditionalInformation('COCNumber');
            $data['companyName'] = $this->getPayment()->getAdditionalInformation('CompanyName');
        }

        return $data;
    }

    protected function getGender(): string
    {
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            return (string)Gender::MALE;
        }
        return (string)Gender::FEMALE;
    }

    public function getCategory(): string
    {
        $category = RecipientCategory::PERSON;
        $billingAddress = $this->getOrder()->getBillingAddress();
        if ($this->getPayment()->getAdditionalInformation('selectedBusiness') == self::BUSINESS_METHOD_B2B) {
            $category = RecipientCategory::COMPANY;
        } else {
            if (
                $this->isCustomerB2B($this->getOrder()->getStoreId()) &&
                !$this->isCompanyEmpty($billingAddress->getCompany())
            ) {
                $category = RecipientCategory::COMPANY;
            }
        }

        return $category;
    }

    /**
     * @throws LocalizedException
     */
    private function isCustomerB2B($storeId = null): bool
    {
        return $this->getConfigData('customer_type', $storeId) !== AfterpayCustomerType::CUSTOMER_TYPE_B2C;
    }

    /**
     * Check if company is empty
     *
     * @param string|null $company
     *
     * @return boolean
     */
    private function isCompanyEmpty(string $company = null): bool
    {
        if (null === $company) {
            return true;
        }

        return strlen(trim($company)) === 0;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|Store $storeId
     * @return mixed
     * @throws LocalizedException
     */
    public function getConfigData(string $field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getOrder()->getStoreId();
        }
        $path = 'payment/' . $this->getPayment()->getMethodInstance()->getCode() . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
