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
namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Common extends Action
{
    /** @var  PageFactory */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Translate\Inline\ParserInterface
     */
    protected $inlineParser;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    protected $logger;

    protected $cart;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Translate\Inline\ParserInterface $inlineParser,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Log $logger,
        \Magento\Checkout\Model\Cart $cart
    )
    {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->inlineParser = $inlineParser;
        $this->logger = $logger;
        $this->cart = $cart;
    }

    public function execute()
    {

    }

    /**
     * @param $address
     * @param $quoteTotals
     *
     * @return array
     */
    public function gatherTotals($address, $quoteTotals)
    {
        $totals = array(
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $address->getData('shipping_incl_tax'),
            'grand_total' => $quoteTotals['grand_total']->getValue()
        );

        return $totals;
    }

    /**
     * @param $wallet
     * @param string $type
     *
     * @return array
     */
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = array(
            'prefix' => '',
            'firstname' => isset($wallet['givenName']) ? $wallet['givenName'] : '',
            'middlename' => '',
            'lastname' => isset($wallet['familyName']) ? $wallet['familyName'] : '',
            'street' => array(
                '0' => isset($wallet['addressLines'][0]) ? $wallet['addressLines'][0] : '',
                '1' => isset($wallet['addressLines'][1]) ? $wallet['addressLines'][1] : null
            ),
            'city' => isset($wallet['locality']) ? $wallet['locality'] : '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '',
            'region' => isset($wallet['administrativeArea']) ? $wallet['administrativeArea'] : '',
            'region_id' => '',
            'postcode' => isset($wallet['postalCode']) ? $wallet['postalCode'] : '',
            'telephone' => isset($wallet['phoneNumber']) ? $wallet['phoneNumber'] : 'N/A',
            'fax' => '',
            'vat_id' => ''
        );

        if ($type == 'shipping') {
            $address['email'] = isset($wallet['emailAddress']) ? $wallet['emailAddress'] : '';
        }

        return $address;
    }

    protected function commonResponse($data, $errorMessage)
    {
        if ($errorMessage || empty($data)) {
            $response = ['success' => 'false'];
        } else {
            $response = ['success' => 'true', 'data' => $data];
        }
        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

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

        $errorFields = array();
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

    protected function getShippingMethods(&$quote, $objectManager)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $quoteRepository = $objectManager->get('Magento\Quote\Model\QuoteRepository');

        $quote->getPayment()->setMethod(\Buckaroo\Magento2\Model\Method\Applepay::PAYMENT_METHOD_CODE);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quoteRepository->save($quote);

        $shippingMethodManagement = $objectManager->get('Magento\Quote\Model\ShippingMethodManagement');
        $shippingMethods = $shippingMethodManagement->estimateByExtendedAddress($quote->getId(), $quote->getShippingAddress());

        if (count($shippingMethods) == 0) {
            $errorMessage = __(
                'Apple Pay payment failed, because no shipping methods were found for the selected address. Please select a different shipping address within the pop-up or within your Apple Pay Wallet.'
            );
            $this->messageManager->addErrorMessage($errorMessage);
        } else {

            foreach ($shippingMethods as $index => $shippingMethod) {
                $shippingMethodsResult[] = [
                    'carrier_title' => $shippingMethod->getCarrierTitle(),
                    'price_incl_tax' => round($shippingMethod->getAmount(), 2),
                    'method_code' => $shippingMethod->getCarrierCode() . '_' .  $shippingMethod->getMethodCode(),
                    'method_title' => $shippingMethod->getMethodTitle(),
                ];
            }

            $this->logger->addDebug(__METHOD__ . '|2|');

            $quote->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
            //$quote->getShippingAddress()->setPaymentMethod(\Buckaroo\Magento2\Model\Method\Applepay::PAYMENT_METHOD_CODE);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $totals = $this->gatherTotals($quote->getShippingAddress(), $quote->getTotals());
            if ($quote->getSubtotal() != $quote->getSubtotalWithDiscount()) {
                $totals['discount'] = round($quote->getSubtotalWithDiscount() - $quote->getSubtotal(), 2);
            }
            $data = [
                'shipping_methods' => $shippingMethodsResult,
                'totals' => $totals
            ];
            $quoteRepository->save($quote);
            $this->cart->save();

            $this->logger->addDebug(__METHOD__ . '|3|');

            return $data;
        }
    }
}