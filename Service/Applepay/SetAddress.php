<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
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

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\TotalsCollector;

class SetAddress
{
    /**
     * @var Log
     */
    protected $logger;
    /**
     * @var TotalsCollector
     */
    protected $totalsCollector;
    /**
     * @var CustomerSession
     */
    protected $customerSession;
    /**
     * Shipping method converter
     *
     * @var ShippingMethodConverter
     */
    protected $converter;
    private ShippingMethodManagement $shippingMethodManagement;

    /**
     * Apple Pay common constructor
     *
     * @param Context $context
     * @param Log $logger
     * @param TotalsCollector $totalsCollector
     * @param ShippingMethodConverter $converter
     * @param CustomerSession|null $customerSession
     */
    public function __construct(
        Context                 $context,
        Log                     $logger,
        TotalsCollector         $totalsCollector,
        ShippingMethodConverter $converter,
        ShippingMethodManagement $shippingMethodManagement,
        CustomerSession         $customerSession = null
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->totalsCollector = $totalsCollector;
        $this->converter = $converter;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->customerSession = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
    }

    /**
     * Get totals
     *
     * @param $address
     * @param $quoteTotals
     * @return array
     */
    public function gatherTotals($address, $quoteTotals)
    {
        $totals = [
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $address->getData('shipping_incl_tax'),
            'grand_total' => $quoteTotals['grand_total']->getValue()
        ];

        return $totals;
    }

    public function getAvailableShippingMethods() {
        return $this->shippingMethodManagement->getShippingMethods(Quote $quote, $address);
    }

    /**
     * Process Address From Wallet
     *
     * @param array $wallet
     * @param string $type
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = [
            'prefix' => '',
            'firstname' => $wallet['givenName'] ?? '',
            'middlename' => '',
            'lastname' => $wallet['familyName'] ?? '',
            'street' => [
                '0' => $wallet['addressLines'][0] ?? '',
                '1' => $wallet['addressLines'][1] ?? null
            ],
            'city' => $wallet['locality'] ?? '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '',
            'region' => $wallet['administrativeArea'] ?? '',
            'region_id' => '',
            'postcode' => $wallet['postalCode'] ?? '',
            'telephone' => $wallet['phoneNumber'] ?? 'N/A',
            'fax' => '',
            'vat_id' => ''
        ];
        $address['street'] = implode("\n", $address['street']);
        if ($type == 'shipping') {
            $address['email'] = $wallet['emailAddress'] ?? '';
        }

        return $address;
    }

    /**
     * Return Json Response from array
     *
     * @param array|string $data
     * @param string|bool $errorMessage
     * @return Json
     */
    protected function commonResponse($data, $errorMessage)
    {
        if ($errorMessage || empty($data)) {
            $response = ['success' => 'false', 'error' => $errorMessage];
        } else {
            $response = ['success' => 'true', 'data' => $data];
        }
        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        return $resultJson->setData($response);
    }

    protected function setShippingAddress(&$quote, $data)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $shippingAddress = $this->processAddressFromWallet($data, 'shipping');
        $quote->getShippingAddress()->addData($shippingAddress);
        $quote->setShippingAddress($quote->getShippingAddress());

        $errors = $quote->getShippingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'shipping');
    }

    protected function setBillingAddress(&$quote, $data)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $billingAddress = $this->processAddressFromWallet($data, 'billing');
        $quote->getBillingAddress()->addData($billingAddress);
        $quote->setBillingAddress($quote->getBillingAddress());

        $errors = $quote->getBillingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'billing');
    }

    protected function setCommonAddressProceed($errors, $addressType)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');
        $this->logger->addDebug(var_export($errors, true));

        $errorFields = [];
        if ($errors && is_array($errors)) {
            foreach ($errors as $error) {
                if (($arguments = $error->getArguments()) && !empty($arguments['fieldName'])) {
                    if ($arguments['fieldName'] === 'postcode') {
                        $errorFields[] = $arguments['fieldName'];
                        $this->logger->addDebug(var_export($error->getArguments()['fieldName'], true));
                        $this->messageManager->addErrorMessage(__(
                            'Error: ' . $addressType . ' address: postcode is required'
                        ));
                    }
                }
            }
        }

        if (empty($errorFields)) {
            $this->logger->addDebug(__METHOD__ . '|2|');
            return true;
        } else {
            $this->logger->addDebug(__METHOD__ . '|3|');
            return false;
        }
    }
}
