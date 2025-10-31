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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Store\Api\Data\StoreInterface;

class Push implements ValidatorInterface
{
    /**
     * @var Account
     */
    public $configProviderAccount;

    /**
     * @var Data
     */
    public $helper;

    /**
     * @var BuckarooLoggerInterface
     */
    public $logger;

    /**
     * @var Encryptor
     */
    private $encryptor;

    /**
     * @var string[]
     */
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
     * @param Data                    $helper
     * @param Account                 $configProviderAccount
     * @param BuckarooLoggerInterface $logger
     * @param Encryptor               $encryptor
     */
    public function __construct(
        Data $helper,
        Account $configProviderAccount,
        BuckarooLoggerInterface $logger,
        Encryptor $encryptor
    ) {
        $this->helper                   = $helper;
        $this->configProviderAccount    = $configProviderAccount;
        $this->logger                   = $logger;
        $this->encryptor                = $encryptor;
    }

    /**
     * Validate push
     *
     * @param  array|object $data
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate($data): bool
    {
        return true;
    }

    /**
     * Checks if the status code is returned by the bpe push and is valid.
     *
     * @param  int|string $code
     * @return array
     */
    public function validateStatusCode($code): array
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
     * Generates and verifies the Buckaroo signature using configuration values and data from a push.
     *
     * @param  array                          $originalPostData
     * @param  array                          $postData
     * @param  int|string|StoreInterface|null $store
     * @throws \Exception
     * @return bool
     */
    public function validateSignature(array $originalPostData, array $postData, $store = null): bool
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
     * @param  array                          $postData
     * @param  int|string|StoreInterface|null $store
     * @throws \Exception
     * @return string
     */
    public function calculateSignature(array $postData, $store = null): string
    {
        $copyData = $postData;
        unset($copyData['brq_signature']);
        unset($copyData['BRQ_SIGNATURE']);

        $sortableArray = $this->buckarooArraySort($copyData);

        $signatureString = '';

        foreach ($sortableArray as $brqKey => $value) {
            $value = $this->decodePushValue($brqKey, $value);

            $signatureString .= $brqKey . '=' . $value;
        }

        $digitalSignature = $this->encryptor->decrypt($this->configProviderAccount->getSecretKey($store));
        $signatureString .= $digitalSignature;
        $signature = SHA1($signatureString);

        $this->logger->addDebug(
            '[PUSH] | [Webapi] | [' . __METHOD__ . ':' . __LINE__ . '] - Calculated signature: ' . $signature,
        );

        return $signature;
    }

    /**
     * Decode push value
     *
     * @param string $brqKey
     * @param string $brqValue
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function decodePushValue(string $brqKey, string $brqValue): string
    {
        switch (strtolower($brqKey)) {
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
            case 'cust_customerbillinglastname':
            case 'cust_customerbillingemail':
            case 'cust_customerbillingstreet':
            case 'cust_customerbillingtelephone':
            case 'cust_customerbillinghousenumber':
            case 'cust_customerbillinghouseadditionalnumber':
            case 'cust_customershippingfirstname':
            case 'cust_customershippinglastname':
            case 'cust_customershippingemail':
            case 'cust_customershippingstreet':
            case 'cust_customershippingtelephone':
            case 'cust_customershippinghousenumber':
            case 'cust_customershippinghouseadditionalnumber':
                $decodedValue = $brqValue;
                break;
            default:
                $decodedValue = urldecode($brqValue);
        }

        return $decodedValue;
    }

    /**
     * Sort the array so that the signature can be calculated identical to the way buckaroo does.
     *
     * @param  array $arrayToUse
     * @return array $sortableArray
     */
    protected function buckarooArraySort(array $arrayToUse): array
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
