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

namespace Buckaroo\Magento2\Gateway\Request\Recipient;

use Buckaroo\Magento2\Model\Config\Source\AfterpayCustomerType;
use Buckaroo\Resources\Constants\RecipientCategory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class AfterpayDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string               $addressType
     */
    public function __construct(ScopeConfigInterface $scopeConfig, string $addressType = 'billing')
    {
        parent::__construct($addressType);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritdoc
     */
    protected function buildData(): array
    {
        $data = [
            'category'             => $this->getCategory(),
            'careOf'               => $this->getCareOf(),
            'firstName'            => $this->getFirstname(),
            'lastName'             => $this->getLastName(),
            'conversationLanguage' => $this->getConversationLanguage()
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

    /**
     * @inheritdoc
     */
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

    /**
     * Determines whether the customer is a B2B customer based on the store configuration.
     *
     * @param int|null $storeId
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    private function isCustomerB2B(?int $storeId = null): bool
    {
        return $this->getConfigData('customer_type', $storeId) !== AfterpayCustomerType::CUSTOMER_TYPE_B2C;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string                $field
     * @param int|string|null|Store $storeId
     *
     * @throws LocalizedException
     *
     * @return mixed
     */
    public function getConfigData(string $field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getOrder()->getStoreId();
        }
        $path = 'payment/' . $this->getPayment()->getMethodInstance()->getCode() . '/' . $field;
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
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
    protected function getCareOf(): string
    {
        return $this->getFirstname() . ' ' . $this->getLastName();
    }

    /**
     * Possible values: NL, FR, DE, FI.
     *
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
     * Retrieves the customer identification number associated with the payment.
     *
     * @return mixed
     */
    protected function getIdentificationNumber()
    {
        return $this->getPayment()->getAdditionalInformation('customer_identificationNumber');
    }
}
