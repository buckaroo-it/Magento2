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
declare(strict_types=1);

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;

abstract class AbstractApplepay implements HttpPostActionInterface
{
    /**
     * @var Log $logging
     */
    public $logging;
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var RequestInterface $request
     */
    protected $request;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param Log $logging
     */
    public function __construct(
        JsonFactory      $resultJsonFactory,
        RequestInterface $request,
        Log              $logging
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->logging = $logging;
    }

    /**
     * Get Params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->request->getParams();
    }

    /**
     * Get totals
     *
     * @param Address $address
     * @param AddressTotal[] $quoteTotals
     * @return array
     */
    public function gatherTotals($address, $quoteTotals): array
    {
        return [
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $address->getData('shipping_incl_tax'),
            'grand_total' => $quoteTotals['grand_total']->getValue()
        ];
    }

    /**
     * Return Json Response from array
     *
     * @param array|string $data
     * @param string|bool $errorMessage
     * @return Json
     */
    protected function commonResponse($data, $errorMessage): Json
    {
        if ($errorMessage || empty($data)) {
            $response = ['success' => 'false', 'error' => $errorMessage];
        } else {
            $response = ['success' => 'true', 'data' => $data];
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
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

    protected function setShippingAddress(&$quote, $data)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        $shippingAddress = $this->processAddressFromWallet($data, 'shipping');
        $quote->getShippingAddress()->addData($shippingAddress);
        $quote->setShippingAddress($quote->getShippingAddress());

        $errors = $quote->getShippingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'shipping');
    }

    protected function setBillingAddress(&$quote, $data)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        $billingAddress = $this->processAddressFromWallet($data, 'billing');
        $quote->getBillingAddress()->addData($billingAddress);
        $quote->setBillingAddress($quote->getBillingAddress());

        $errors = $quote->getBillingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'billing');
    }

    protected function setCommonAddressProceed($errors, $addressType)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        $this->logging->addDebug(var_export($errors, true));

        $errorFields = [];
        if ($errors && is_array($errors)) {
            foreach ($errors as $error) {
                if (($arguments = $error->getArguments()) && !empty($arguments['fieldName'])) {
                    if ($arguments['fieldName'] === 'postcode') {
                        $errorFields[] = $arguments['fieldName'];
                        $this->logging->addDebug(var_export($error->getArguments()['fieldName'], true));
                    }
                }
            }
        }

        if (empty($errorFields)) {
            $this->logging->addDebug(__METHOD__ . '|2|');
            return true;
        } else {
            $this->logging->addDebug(__METHOD__ . '|3|');
            return false;
        }
    }
}
