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
use Buckaroo\Resources\Constants\Gender;
use Buckaroo\Resources\Constants\RecipientCategory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class AfterpayOldDataBuilder extends AbstractRecipientDataBuilder
{
    /**
     * Business methods that will be used in afterpay.
     */
    public const BUSINESS_METHOD_B2C = 1;
    public const BUSINESS_METHOD_B2B = 2;

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
            'title'     => $this->getFirstname(),
            'initials'  => $this->getInitials(),
            'lastName'  => $this->getLastName(),
            'birthDate' => $this->getBirthDate(),
            'culture'   => $this->getOrder()->getBillingAddress()->getCountryId()
        ];

        if ($this->getCategory() == RecipientCategory::COMPANY) {
            $data['chamberOfCommerce'] = $this->getPayment()->getAdditionalInformation('COCNumber');
            $data['companyName'] = $this->getPayment()->getAdditionalInformation('CompanyName');
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getCategory($order = null, $payment = null): string
    {
        $category = RecipientCategory::PERSON;

        if ($order === null) {
            $order = $this->getOrder();
        } else {
            $this->setOrder($order);
        }

        if ($payment === null) {
            $payment = $this->getPayment();
        } else {
            $this->setPayment($payment);
        }

        $billingAddress = $order->getBillingAddress();
        if ($payment->getAdditionalInformation('selectedBusiness') == self::BUSINESS_METHOD_B2B) {
            $category = RecipientCategory::COMPANY;
        } else {
            if ($this->isCustomerB2B($order->getStoreId()) &&
                !$this->isCompanyEmpty($billingAddress->getCompany())
            ) {
                $category = RecipientCategory::COMPANY;
            }
        }

        return $category;
    }

    /**
     * Determines whether the customer is a B2B customer based on the store configuration.
     *
     * @param  int|string|null    $storeId
     * @throws LocalizedException
     * @return bool
     */
    private function isCustomerB2B($storeId = null): bool
    {
        return $this->getConfigData('customer_type', $storeId) !== AfterpayCustomerType::CUSTOMER_TYPE_B2C;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param  string                $field
     * @param  int|string|null|Store $storeId
     * @throws LocalizedException
     * @return mixed
     */
    public function getConfigData(string $field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getOrder()->getStoreId();
        }
        $path = 'payment/' . $this->getPayment()->getMethodInstance()->getCode() . '/' . $field;
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
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
    protected function getGender(): string
    {
        if ($this->payment->getAdditionalInformation('customer_gender') === '1') {
            return (string)Gender::MALE;
        }
        return (string)Gender::FEMALE;
    }
}
