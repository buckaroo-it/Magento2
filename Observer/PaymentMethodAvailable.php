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

namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Payment\Helper\Data;
use Magento\Framework\Event\Observer;
use Buckaroo\Magento2\Logging\Log;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Group;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\App\Request\Http;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Buckaroo\Magento2\Model\Config\Source\Business;

class PaymentMethodAvailable implements \Magento\Framework\Event\ObserverInterface
{
    private $paymentHelper;
    private $logging;
    private $customerSession;
    private $configProviderMethodFactory;
    private $state;
    private $request;
    private $customerRepository;

    /**
     * @param Log $logging
     * @param Factory $configProviderMethodFactory
     */
    public function __construct(
        Data $paymentHelper,
        Log $logging,
        Session $customerSession,
        Factory $configProviderMethodFactory,
        State $state,
        Http $request,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->logging = $logging;
        $this->customerSession = $customerSession;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->state = $state;
        $this->request = $request;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $method = $observer->getMethodInstance();

        if (!$this->checkCustomerGroup($observer, $method->getCode())) {
            $this->setNotAvailableResult($observer);
            return false;
        }

        if ($method->getCode() !== 'buckaroo_magento2_pospayment') {
            $pospaymentMethodInstance = $this->paymentHelper->getMethodInstance('buckaroo_magento2_pospayment');
            if ($pospaymentMethodInstance->isAvailable($observer->getEvent()->getQuote())) {
                $showMethod = false;
                //check custom set payment methods what should be visible in addition to POS
                if ($otherPaymentMethods = $pospaymentMethodInstance->getOtherPaymentMethods()) {
                    if ($this->isBuckarooMethod($method->getCode())) {
                        if (in_array(
                            $this->getBuckarooMethod($method->getCode()),
                            explode(',', $otherPaymentMethods)
                        )) {
                            $showMethod = true;
                        }
                    }
                }

                if (!$showMethod) {
                    $this->setNotAvailableResult($observer);
                }
            }
        }
    }

    private function checkCustomerGroup(Observer $observer, string $paymentMethod): bool
    {
        if ($this->isBuckarooMethod($paymentMethod)) {
            $paymentMethodCode = $this->getBuckarooMethod($paymentMethod);
            $configProvider = $this->configProviderMethodFactory->get($paymentMethodCode);
            $configCustomerGroup = $configProvider->getSpecificCustomerGroup();

            if (
                (
                    ($paymentMethodCode == 'billink')
                    && $observer->getEvent()->getQuote()
                    && $observer->getEvent()->getQuote()->getBillingAddress()
                    && $observer->getEvent()->getQuote()->getBillingAddress()->getCompany()
                )
                || (
                    (($paymentMethodCode == 'afterpay') || ($paymentMethodCode == 'afterpay2'))
                    && ($configProvider->getBusiness() == Business::BUSINESS_B2B)
                )
                || (
                    ($paymentMethodCode == 'payperemail') && ($configProvider->getEnabledB2B())
                )
            ) {
                $configCustomerGroup = $configProvider->getSpecificCustomerGroupB2B();

            }

            //$this->logging->addDebug(__METHOD__ . '|5|' . var_export([$paymentMethodCode, $configCustomerGroup], true));

            if ($configCustomerGroup === null) {
                return true;
            }

            if ($configCustomerGroup == -1) {
                return false;
            }

            if ($configCustomerGroup == Group::CUST_GROUP_ALL) {
                return true;
            }

            $configCustomerGroupArr = explode(',', $configCustomerGroup);

            if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
                return $this->checkCustomerGroupAdminArea($configCustomerGroupArr);
            } else {
                return $this->checkCustomerGroupFrontArea($configCustomerGroupArr);
            }
        }

        return true;
    }

    private function checkCustomerGroupAdminArea(array $configCustomerGroupArr): bool
    {
        if (($customerId = $this->request->getParam('customer_id')) && ($customerId > 0)) {
            if ($customer = $this->customerRepository->getById($customerId)) {
                if ($customerGroup = $customer->getGroupId()) {
                    return in_array($customerGroup, $configCustomerGroupArr);
                }
            }
        }
        return true;
    }

    private function checkCustomerGroupFrontArea(array $configCustomerGroupArr): bool
    {
        if ($this->customerSession->isLoggedIn()) {
            if ($customerGroup = $this->customerSession->getCustomer()->getGroupId()) {
                return in_array($customerGroup, $configCustomerGroupArr);
            }
        } else {
            if (!in_array(Group::NOT_LOGGED_IN_ID, $configCustomerGroupArr)) {
                return false;
            }
        }
        return true;
    }

    private function setNotAvailableResult(Observer $observer)
    {
        $observer->getEvent()->getResult()->setData('is_available', false);
    }

    private function isBuckarooMethod(string $paymentMethod): bool
    {
        return strpos($paymentMethod, 'buckaroo_magento2_') !== false;
    }

    private function getBuckarooMethod(string $paymentMethod): string
    {
        return strtolower(str_replace('buckaroo_magento2_','', $paymentMethod));
    }
}
