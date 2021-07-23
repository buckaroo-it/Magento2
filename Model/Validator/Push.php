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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use \Buckaroo\Magento2\Model\ValidatorInterface;
use \Magento\Framework\Encryption\Encryptor;

/**
 * Class Push
 *
 * @package Buckaroo\Magento2\Model\Validator
 */
class Push implements ValidatorInterface
{
    /** @var Account $configProviderAccount */
    public $configProviderAccount;

    /** @var Data $helper */
    public $helper;

    /** @var Log $logging */
    public $logging;

    /** @var Encryptor $encryptor */
    private $encryptor;

    public $bpeResponseMessages = [
        190 => 'Success',
        490 => 'Payment failure',
        491 => 'Validation error',
        492 => 'Technical error',
        690 => 'Payment rejected',
        790 => 'Waiting for user input',
        791 => 'Waiting for processor',
        792 => 'Waiting on consumer action',
        793 => 'Payment on hold',
        890 => 'Cancelled by consumer',
        891 => 'Cancelled by merchant'
    ];

    /**
     * @param Data          $helper
     * @param Account       $configProviderAccount
     * @param Log           $logging
     * @param Encryptor     $encryptor
     */
    public function __construct(
        Data $helper,
        Account $configProviderAccount,
        Log $logging,
        Encryptor $encryptor
    ) {
        $this->helper                   = $helper;
        $this->configProviderAccount    = $configProviderAccount;
        $this->logging                  = $logging;
        $this->encryptor                = $encryptor;
    }

    /**
     * @param $data
     *
     * @return bool
     */
    public function validate($data)
    {
        return true;
    }

    /**
     * Checks if the status code is returned by the bpe push and is valid.
     *
     * @param $code
     *
     * @return array
     */
    public function validateStatusCode($code)
    {
        if (null !== $this->helper->getStatusByValue($code)
            && isset($this->bpeResponseMessages[$code])
        ) {
            return [
                'message' => $this->bpeResponseMessages[$code],
                'status'  => $this->helper->getStatusByValue($code),
                'code'    => $code,
            ];
        } else {
            return [
                'message' => 'Onbekende responsecode: ' . $code,
                'status'  => 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL',
                'code'    => $code,
            ];
        }
    }

    /**
     * Generate/calculate the signature with the buckaroo config value and check if thats equal to the signature
     * received from the push
     *
     * @param $postData
     *
     * @return bool
     */
    public function validateSignature($originalPostData, $postData, $store = null)
    {
        if (!isset($postData['brq_signature'])) {
            return false;
        }

        $signature = $this->calculateSignature($originalPostData, $store);

        if ($signature !== $postData['brq_signature']) {
            return false;
        }

        return true;
    }

    /**
     * Determines the signature using array sorting and the SHA1 hash algorithm
     *
     * @param $postData
     *
     * @return string
     */
    protected function calculateSignature($postData, $store = null)
    {
        $copyData = $postData;
        unset($copyData['brq_signature']); unset($copyData['BRQ_SIGNATURE']);

        $sortableArray = $this->buckarooArraySort($copyData);

        $signatureString = '';

        foreach ($sortableArray as $brq_key => $value) {
            $value = $this->decodePushValue($brq_key, $value);

            $signatureString .= $brq_key. '=' . $value;
        }

        $digitalSignature = $this->encryptor->decrypt($this->configProviderAccount->getSecretKey($store));

        $signatureString .= $digitalSignature;

        $signature = SHA1($signatureString);

        $this->logging->addDebug($signature);

        return $signature;
    }

    /**
     * @param string $brq_key
     * @param string $brq_value
     *
     * @return string
     */
    private function decodePushValue($brq_key, $brq_value)
    {
        switch (strtolower($brq_key)) {
            case 'brq_customer_name':
            case 'brq_service_ideal_consumername':
            case 'brq_service_transfer_consumername':
            case 'brq_service_payconiq_payconiqandroidurl':
            case 'brq_service_paypal_payeremail':
            case 'brq_service_paypal_payerfirstname':
            case 'brq_service_paypal_payerlastname':
            case 'brq_service_payconiq_payconiqiosurl':
            case 'brq_service_payconiq_payconiqurl':
            case 'brq_service_payconiq_qrurl':
            case 'brq_service_masterpass_customerphonenumber':
            case 'brq_service_masterpass_shippingrecipientphonenumber':
            case 'brq_invoicedate':
            case 'brq_duedate':
            case 'brq_previousstepdatetime':
            case 'brq_eventdatetime':
            case 'brq_service_transfer_accountholdername':
            case 'brq_service_transfer_customeraccountname':
            case 'cust_customerbillingfirstname':
            case 'cust_customerbillingemail':
            case 'cust_customerbillingstreet':
            case 'cust_customerbillingtelephone':
                $decodedValue = $brq_value;
                break;
            default:
                $decodedValue = urldecode($brq_value);
        }

        return $decodedValue;
    }

    /**
     * Sort the array so that the signature can be calculated identical to the way buckaroo does.
     *
     * @param $arrayToUse
     *
     * @return array $sortableArray
     */
    protected function buckarooArraySort($arrayToUse)
    {
        $arrayToSort   = [];
        $originalArray = [];

        foreach ($arrayToUse as $key => $value) {
            $arrayToSort[strtolower($key)]   = $value;
            $originalArray[strtolower($key)] = $key;
        }

        ksort($arrayToSort);

        $sortableArray = [];

        foreach ($arrayToSort as $key => $value) {
            $key = $originalArray[$key];
            $sortableArray[$key] = $value;
        }

        return $sortableArray;
    }
}
