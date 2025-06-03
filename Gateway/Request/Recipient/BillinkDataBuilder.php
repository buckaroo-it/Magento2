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

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\Config\Source\BillinkCustomerType;
use Buckaroo\Resources\Constants\RecipientCategory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;

class BillinkDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var Data
     */
    public Data $helper;

    /**
     * @param Data $helper
     * @param string $addressType
     */
    public function __construct(Data $helper, ScopeConfigInterface $scopeConfig, string $addressType = 'billing')
    {
        parent::__construct($addressType);
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
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
            'birthDate' => $this->getBirthDate()
        ];

        if ($category == 'B2B') {
            $data['chamberOfCommerce'] = $this->getChamberOfCommerce();
        }

        return $data;
    }

    /**
     * Returns the birthdate of the customer
     *
     * @return false|string
     */
    protected function getBirthDate()
    {
        $customerDoB = (string)$this->payment->getAdditionalInformation('customer_DoB');
        if (empty($customerDoB)) {
            $customerDoB = $this->getOrder()->getCustomerDob() ?? '1990-01-01';
        }

        if(!is_string($customerDoB) || strlen(trim($customerDoB)) === 0) {
            return null;
        }

        $birthDayStamp = date(
            $this->getFormatDate(),
            strtotime(str_replace('/', '-', $customerDoB))
        );

        if($birthDayStamp === false) {
            return null;
        }

        return $birthDayStamp;
    }

    /**
     * Determines whether the customer is a B2B customer based on the store configuration.
     *
     * @param int|null $storeId
     * @return bool
     * @throws LocalizedException
     */
    private function isCustomerB2B(int $storeId = null): bool
    {
        return $this->getConfigData('customer_type', $storeId) !== BillinkCustomerType::CUSTOMER_TYPE_B2C;
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
     * @inheritdoc
     */
    protected function getCategory(): string
    {
        $category = 'B2C';
        $billingAddress = $this->getOrder()->getBillingAddress();

        if ($this->isCustomerB2B($this->getOrder()->getStoreId()) &&
            !$this->isCompanyEmpty($billingAddress->getCompany())
        ) {
            $category = 'B2B';
        }

        return $category;
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
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            return 'Male';
        } elseif ($this->payment->getAdditionalInformation('customer_gender') === '2') {
            return 'Female';
        } else {
            return 'Unknown';
        }
    }
}
