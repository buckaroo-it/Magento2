<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Gateway\Request\Recipient\AbstractRecipientDataBuilder;
use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;
use Buckaroo\Resources\Constants\RecipientCategory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class AfterpayDataBuilder extends AbstractRecipientDataBuilder
{
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig, string $addressType = 'billing')
    {
        parent::__construct($addressType);
        $this->scopeConfig = $scopeConfig;
    }

    protected function buildData(): array
    {
        $data = [
            'category' => $this->getCategory(),
            'careOf' => $this->getCareOf(),
            'firstName' => $this->getFirstname(),
            'lastName' => $this->getLastName(),
            'conversationLanguage' => $this->getConversationLanguage(),
            'customerNumber' => 'customerNumber12345'
        ];

        $category = $this->getCategory();
        $billingAddress = $this->getOrder()->getBillingAddress();
        if ($category === RecipientCategory::PERSON) {
            if ($billingAddress->getCountryId() == 'NL' || $billingAddress->getCountryId() == 'BE') {
                $data['title'] = $this->getTitle();
                $data['birthDate'] = $this->getBirthDate();
            }
            if ($billingAddress->getCountryId() == 'FI') {
                $data['identificationNumber'] = $this->getIdentificationNumber();
            }
        } else {
            $data['companyName'] = $billingAddress->getCompany();
            $data['identificationNumber'] = $this->getIdentificationNumber();
        }

        return $data;
    }

    protected function getCategory(): string
    {
        $category = RecipientCategory::PERSON;
        $billingAddress = $this->getOrder()->getBillingAddress();

        if ($this->isCustomerB2B($this->getOrder()->getStoreId()) &&
            $billingAddress->getCountryId() === 'NL' &&
            !$this->isCompanyEmpty($billingAddress->getCompany())
        ) {
            $category = RecipientCategory::COMPANY;
        }

        return $category;
    }

    protected function getCareOf(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastName();
    }

    protected function getIdentificationNumber()
    {
        return $this->getPayment()->getAdditionalInformation('customer_identificationNumber');
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
     * Possible values: NL, FR, DE, FI.
     * @return string
     */
    private function getConversationLanguage(): string
    {
        $countryId = $this->getOrder()->getBillingAddress()->getCountryId();

        if (in_array($countryId, ['NL', 'FR', 'DE', 'FI'])) {
            return $countryId;
        } else {
            return 'NL';
        }
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
