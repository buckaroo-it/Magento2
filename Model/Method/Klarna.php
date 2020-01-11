<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-wewe b at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Model\Method;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use TIG\Buckaroo\Service\Software\Data as SoftwareData;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Checkout\Model\Cart;
use Zend_Locale;


class Klarna extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_klarna';

    /**
     * Check if the tax calculation includes tax.
     */
    const TAX_CALCULATION_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const TAX_CALCULATION_SHIPPING_INCLUDES_TAX = 'tax/calculation/shipping_includes_tax';

    /** Klarna Article Types */
    const KLARNA_ARTICLE_TYPE_GENERAL = 'General';
    const KLARNA_ARTICLE_TYPE_HANDLINGFEE = 'HandlingFee';
    const KLARNA_ARTICLE_TYPE_SHIPMENTFEE = 'ShipmentFee';


    /**
     * Business methods that will be used in klarna.
     */
    const BUSINESS_METHOD_B2C = 1;
    const BUSINESS_METHOD_B2B = 2;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'klarna';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canVoid = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * @var bool
     */
    public $closeAuthorizeTransaction   = false;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    // @codingStandardsIgnoreEnd

    /** @var SoftwareData */
    private $softwareData;

    /** @var Calculation */
    private $taxCalculation;

    /** @var Config */
    private $taxConfig;

    /** @var Cart */
    private $cart;

    /** @var \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee */
    protected $configProviderBuckarooFee;

    /** @var \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna */
    protected $configProviderKlarna;

    protected $session;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Developer\Helper\Data $developmentHelper
     * @param \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna $configProviderKlarna
     * @param SoftwareData $softwareData
     * @param Config $taxConfig
     * @param Calculation $taxCalculation
     * @param Cart $cart
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param \TIG\Buckaroo\Gateway\GatewayInterface $gateway
     * @param \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory
     * @param \TIG\Buckaroo\Model\ValidatorFactory $validatorFactory
     * @param \TIG\Buckaroo\Helper\Data $helper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \TIG\Buckaroo\Model\RefundFieldsFactory $refundFieldsFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Factory $configProviderFactory
     * @param \TIG\Buckaroo\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Developer\Helper\Data $developmentHelper,
        \TIG\Buckaroo\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        \TIG\Buckaroo\Model\ConfigProvider\Method\Klarna $configProviderKlarna,
        SoftwareData $softwareData,
        Config $taxConfig,
        Calculation $taxCalculation,
        Cart $cart,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \TIG\Buckaroo\Gateway\GatewayInterface $gateway = null,
        \TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \TIG\Buckaroo\Model\ValidatorFactory $validatorFactory = null,
        \TIG\Buckaroo\Helper\Data $helper = null,
        \Magento\Framework\App\RequestInterface $request = null,
        \TIG\Buckaroo\Model\RefundFieldsFactory $refundFieldsFactory = null,
        \TIG\Buckaroo\Model\ConfigProvider\Factory $configProviderFactory = null,
        \TIG\Buckaroo\Model\ConfigProvider\Method\Factory $configProviderMethodFactory = null,
        \Magento\Framework\Pricing\Helper\Data $priceHelper = null,
        array $data = []
    ) {
        parent::__construct(
            $objectManager,
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $developmentHelper,
            $resource,
            $resourceCollection,
            $gateway,
            $transactionBuilderFactory,
            $validatorFactory,
            $helper,
            $request,
            $refundFieldsFactory,
            $configProviderFactory,
            $configProviderMethodFactory,
            $priceHelper,
            $data
        );

        $this->configProviderBuckarooFee = $configProviderBuckarooFee;
        $this->configProviderKlarna = $configProviderKlarna;
        $this->softwareData = $softwareData;
        $this->taxConfig = $taxConfig;
        $this->taxCalculation = $taxCalculation;
        $this->cart = $cart;
        $this->session = $session;
    }

    /**
     * Check capture availability
     *
     * @return bool
     * @api
     */
    public function canCapture()
    {
        if ($this->getConfigData('payment_action') == 'order') {
            return false;
        }
        return $this->_canCapture;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {

    }

    public function cancelOrderTransactionBuilder($payment)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name' => 'klarna',
            'Action' => 'Reserve',
            'Version' => 1,
            'RequestParameter' => $this->getKlarnaRequestParameters($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('DataRequest');

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function getCaptureTransactionBuilder($payment)
    {
        $group = 1;
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $capturePartial = false;

        $order = $payment->getOrder();
        $order_id = $order->getId();

        $totalOrder = $order->getBaseGrandTotal();

        $numberOfInvoices = $order->getInvoiceCollection()->count();
        $currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }

                $currentInvoice = $oInvoice;
                $currentInvoiceTotal = $oInvoice->getBaseGrandTotal();
            }
        }

        if ($totalOrder == $currentInvoiceTotal && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        } else {
            //partial capture
            $capturePartial = true;
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $services = [
            'Name' => 'klarna',
            'Action' => 'Pay',
            'Version' => 1,
        ];

        // add additional information
        $articles = $this->getAdditionalInformation($payment);

        // always get articles from invoice
        if (isset($currentInvoice)) {
            $articledata = $this->getPayRequestData($currentInvoice, $payment);
            $articles = array_merge($articles, $articledata);
            $group++;
        }

        // For the first invoice possible add payment fee
        if (is_array($articles) && $numberOfInvoices == 1) {
            $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);
            $serviceLine = $this->getServiceCostLine($currentInvoice, $includesTax, $group);
            if (!empty($serviceLine)) {
                unset($serviceLine[1]);
                unset($serviceLine[3]);
                unset($serviceLine[4]);
                $articles = array_merge($articles, $serviceLine);
                $group++;
            }
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($currentInvoice, $group);

        if (!empty($shippingCosts)) {
            unset($shippingCosts[1]);
            unset($shippingCosts[3]);
            unset($shippingCosts[4]);
            $articles = array_merge($articles, $shippingCosts);
            $group++;
        }

        $services['RequestParameter'] = $articles;

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setAmount($currentInvoiceTotal)
            ->setMethod('TransactionRequest')
            ->setCurrency($this->payment->getOrder()->getOrderCurrencyCode());

        // Partial Capture Settings
        if ($capturePartial) {
            $transactionBuilder->setInvoiceId($payment->getOrder()->getIncrementId(). '-' . $numberOfInvoices)
                ->setOriginalTransactionKey($payment->getParentTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function getRefundTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('refund');

        $capturePartial = false;

        $order = $payment->getOrder();
        $order_id = $order->getId();

        $totalOrder = $order->getBaseGrandTotal();

        $numberOfInvoices = $order->getInvoiceCollection()->count();
        $currentInvoiceTotal = 0;

        // loop through invoices to get the last one (=current invoice)
        if ($numberOfInvoices) {
            $oInvoiceCollection = $order->getInvoiceCollection();

            $i = 0;
            foreach ($oInvoiceCollection as $oInvoice) {
                if (++$i !== $numberOfInvoices) {
                    continue;
                }

                $currentInvoice = $oInvoice;
                $currentInvoiceTotal = $oInvoice->getBaseGrandTotal();
            }
        }

        if ($totalOrder == $currentInvoiceTotal && $numberOfInvoices == 1) {
            //full capture
            $capturePartial = false;
        } else {
            //partial capture
            $capturePartial = true;
        }

        $services = [
            'Name'    => 'klarna',
            'Action'  => 'Refund',
            'Version' => 1,
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest')
            ->setOriginalTransactionKey($payment->getParentTransactionId());

        // Partial Capture Settings
        if ($capturePartial) {
            $transactionBuilder->setInvoiceId($payment->getOrder()->getIncrementId(). '-' . $numberOfInvoices)
                ->setOriginalTransactionKey($payment->getParentTransactionId());
        }

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return \TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface|bool
     * @throws \TIG\Buckaroo\Exception
     */
    public function getVoidTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name' => 'klarna',
            'Action' => 'CancelReservation',
            'Version' => 1,
            'RequestParameter' => $this->getCancelReservationData($payment),
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('DataRequest');

        return $transactionBuilder;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestShippingData($payment)
    {
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $shippingAddress
         */
        $shippingAddress = $payment->getOrder()->getShippingAddress();
        $streetFormat = $this->formatStreet($shippingAddress->getStreet());
        $shippingSameAsBilling = $this->isAddressDataDifferent($payment);
        $additionalFields = $this->session->getData('additionalFields');


        $rawPhoneNumber = $shippingAddress->getTelephone();
        if (!is_numeric($rawPhoneNumber) || $rawPhoneNumber == '-') {
            $rawPhoneNumber = $additionalFields['BPE_customer_phonenumber'];
        }

        $phoneNumber = $this->processPhoneNumber($rawPhoneNumber);
        if ($shippingAddress->getCountryId() == 'BE') {
            $phoneNumber = $this->processPhoneNumberBe($rawPhoneNumber);
        }

        $shippingData = [
            [
                '_' => $shippingSameAsBilling,
                'Name' => 'ShippingSameAsBilling',
            ],
            [
                '_' => $phoneNumber['clean'],
                'Name' => 'ShippingCellPhoneNumber',
            ],
            [
                '_' => $shippingAddress->getCity(),
                'Name' => 'ShippingCity',
            ],
            [
                '_' => $shippingAddress->getCountryId(),
                'Name' => 'ShippingCountry',
            ],
            [
                '_' => $shippingAddress->getEmail(),
                'Name' => 'ShippingEmail',
            ],
            [
                '_' => $shippingAddress->getFirstname(),
                'Name' => 'ShippingFirstName',
            ],
            [
                '_' => $shippingAddress->getLastName(),
                'Name' => 'ShippingLastName',
            ],
            [
                '_' => $shippingAddress->getTelephone(),
                'Name' => 'ShippingPhoneNumber',
            ],
            [
                '_' => $shippingAddress->getPostcode(),
                'Name' => 'ShippingPostalCode',
            ],
            [
                '_' => $streetFormat['street'],
                'Name' => 'ShippingStreet',
            ],
        ];


        if (!empty($streetFormat['house_number'])) {
            $shippingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'ShippingHouseNumber',
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $shippingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'ShippingHouseNumberSuffix',
            ];
        }

        return $shippingData;
    }

    /**
     * The final output should look like 0031123456789 or 0031612345678
     * So 13 characters max else number is not valid
     *
     * @param $telephoneNumber
     *
     * @return array
     */
    private function processPhoneNumber($telephoneNumber)
    {
        $number = $telephoneNumber;

        //strip out the non-numeric characters:
        $match = preg_replace('/[^0-9]/Uis', '', $number);
        if ($match) {
            $number = $match;
        }

        $return = array(
            "orginal" => $number,
            "clean" => false,
            "mobile" => $this->_isMobileNumber($number),
            "valid" => false
        );
        $numberLength = strlen((string)$number);

        if ($numberLength == 13) {
            $return['valid'] = true;
            $return['clean'] = $number;
        } elseif ($numberLength > 13 || $numberLength == 12 || $numberLength == 11) {
            $return['clean'] = $this->_isValidNotation($number);

            if (strlen((string)$return['clean']) == 13) {
                $return['valid'] = true;
            }
        } elseif ($numberLength == 10) {
            $return['clean'] = '0031' . substr($number, 1);

            if (strlen((string) $return['clean']) == 13) {
                $return['valid'] = true;
            }
        } else {
            $return['valid'] = true;
            $return['clean'] = $number;
        }

        return $return;
    }

    /**
     * validate the phonenumber
     *
     * @param $number
     * @return mixed
     */
    protected function _isValidNotation($number) {
        //checks if the number is valid, if not: try to fix it
        $invalidNotations = array("00310", "0310", "310", "31");
        foreach($invalidNotations as $invalid) {
            if( strpos( substr( $number, 0, strlen($invalid) ), $invalid ) !== false ) {
                $valid = substr($invalid, 0, -1);
                if (substr($valid, 0, 2) == '31') {
                    $valid = "00" . $valid;
                }
                if (substr($valid, 0, 2) == '03') {
                    $valid = "0" . $valid;
                }
                if ($valid == '3'){
                    $valid = "0" . $valid . "1";
                }
                $number = substr_replace($number, $valid, 0, strlen($invalid));
            }
        }
        return $number;
    }


    /**
     * The final output should look like: 003212345678 or 0032461234567
     *
     * @param $telephoneNumber
     *
     * @return array
     */
    private function processPhoneNumberBe($telephoneNumber)
    {
        $number = $telephoneNumber;

        //strip out the non-numeric characters:
        $match = preg_replace('/[^0-9]/Uis', '', $number);
        if ($match) {
            $number = $match;
        }

        $return = array(
            "orginal" => $number,
            "clean" => false,
            "mobile" => $this->_isMobileNumberBe($number),
            "valid" => false
        );
        $numberLength = strlen((string)$number);

        if (($return['mobile'] && $numberLength == 13) || (!$return['mobile'] && $numberLength == 12)) {
            $return['valid'] = true;
            $return['clean'] = $number;
        } elseif ($numberLength > 13
            || (!$return['mobile'] && $numberLength > 12)
            || ($return['mobile'] && ($numberLength == 11 || $numberLength == 12))
            || (!$return['mobile'] && ($numberLength == 10 || $numberLength == 11))
        ) {
            $return['clean'] = $this->_isValidNotationBe($number);
            $cleanLength = strlen((string)$return['clean']);

            if (($return['mobile'] && $cleanLength == 13) || (!$return['mobile'] && $cleanLength == 12)) {
                $return['valid'] = true;
            }
        } elseif (($return['mobile'] && $numberLength == 10) || (!$return['mobile'] && $numberLength == 9)) {
            $return['clean'] = '0032'.substr($number, 1);
            $cleanLength = strlen((string)$return['clean']);

            if (($return['mobile'] && $cleanLength == 13) || (!$return['mobile'] && $cleanLength == 12)) {
                $return['valid'] = true;
            }
        } else {
            $return['valid'] = true;
            $return['clean'] = $number;
        }

        return $return;
    }

    /**
     * Checks if the number is a mobile number or not.
     *
     * @param string $number
     *
     * @return boolean
     */
    protected function _isMobileNumber($number) {
        //this function only checks if it is a mobile number, not checking valid notation
        $checkMobileArray = array("3106","316","06","00316","003106");
        foreach($checkMobileArray as $key => $value) {

            if(strpos(substr($number, 0, strlen($value)), $value) !== false) {

                return true;
            }
        }
        return false;
    }

    /**
     * Checks if the number is a BE mobile number or not.
     *
     * @param string $number
     *
     * @return boolean
     */
    protected function _isMobileNumberBe($number) {
        //this function only checks if it is a BE mobile number, not checking valid notation
        $checkMobileArray = array(
            "3246","32046","046","003246","0032046",
            "3247","32407","047","003247","0032047",
            "3248","32048","048","003248","0032048",
            "3249","32049","049","003249","0032049"
        );

        foreach ($checkMobileArray as $key => $value) {
            if (strpos(substr($number, 0, strlen($value)), $value) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * validate the BE phonenumber
     *
     * @param $number
     * @return mixed
     */
    protected function _isValidNotationBe($number) {
        //checks if the number is valid, if not: try to fix it
        $invalidNotations = array("00320", "0320", "320", "32");

        foreach ($invalidNotations as $invalid) {
            if (strpos(substr($number, 0, strlen($invalid)), $invalid) !== false) {
                $valid = substr($invalid, 0, -1);
                if (substr($valid, 0, 2) == '32') {
                    $valid = "00" . $valid;
                }
                if (substr($valid, 0, 2) == '03') {
                    $valid = "0" . $valid;
                }
                if ($valid == '3') {
                    $valid = "0" . $valid . "2";
                }
                $number = substr_replace($number, $valid, 0, strlen($invalid));
            }
        }

        return $number;
    }

    /**
     * @param $invoice
     * @param $payment
     * @return array
     */
    public function getPayRequestData($invoice, $payment)
    {
        $order = $payment->getOrder();
        $invoiceCollection = $order->getInvoiceCollection();

        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        $articles = array();
        $group = 1;

        $invoiceItems = $invoice->getAllItems();

        foreach ($invoiceItems as $item) {
            if (empty($item) || $this->calculateProductPrice($item, $includesTax) == 0) {
                continue;
            }

            $articles[] = [
                '_' => $item->getSku(),
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleNumber',
            ];

            $articles[] = [
                '_' => (int) $item->getQty(),
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleQuantity',
            ];

            $group++;
        }

        $discountline = $this->getDiscountLine($payment, $group);

        if (false !== $discountline && is_array($discountline) && count($invoiceCollection) == 1) {
            unset($discountline[1]);
            unset($discountline[3]);
            unset($discountline[4]);
            $articles = array_merge($articles, $discountline);
            $group++;
        }

        return $articles;
    }

    public function getCancelReservationData($payment)
    {
        $order = $payment->getOrder();

        $reservationr = [
            [
                '_'    => $order->getBuckarooReservationNumber(),
                'Name' => 'ReservationNumber',
            ]
        ];

        return $reservationr;
    }

    /**
     * {@inheritdoc}
     */
    public function processCustomPostData($payment, $postData)
    {
        $order = $payment->getOrder();

        if ($order->getBuckarooReservationNumber()) {
            return;
        }

        $order->setBuckarooReservationNumber($postData->Services->Service->ResponseParameter->_);
        $order->save();
    }

    public function getAdditionalInformation($payment)
    {
        $order = $payment->getOrder();

        $additionalinformation = [
            [
                '_' => !$this->checkInvoiceSendByEmail() ? 'true' : 'false',
                'Name' => 'SendByMail',
            ],
            [
                '_' => $this->checkInvoiceSendByEmail() ? 'true' : 'false' ,
                'Name' => 'SendByEmail',
            ],
            [
                '_' => $order->getBuckarooReservationNumber(),
                'Name' => 'ReservationNumber',
            ]
        ];

        return $additionalinformation;
    }

    /**
     * @return string
     */
    private function checkInvoiceSendByEmail()
    {
        return (string)$this->configProviderKlarna->getInvoiceSendMethod() === 'email';
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['termsCondition'])) {
            $additionalData = $data['additional_data'];
            $this->getInfoInstance()->setAdditionalInformation('termsCondition', $additionalData['termsCondition']);
            $this->getInfoInstance()->setAdditionalInformation('customer_gender', $additionalData['customer_gender']);
            $this->getInfoInstance()->setAdditionalInformation(
                'customer_billingName',
                $additionalData['customer_billingName']
            );
            $this->getInfoInstance()->setAdditionalInformation('customer_iban', $additionalData['customer_iban']);

            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('d-m-Y'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);

            if (isset($additionalData['selectedBusiness'])
                && $additionalData['selectedBusiness'] == self::BUSINESS_METHOD_B2B
            ) {
                $this->getInfoInstance()->setAdditionalInformation('COCNumber', $additionalData['COCNumber']);
                $this->getInfoInstance()->setAdditionalInformation('CompanyName', $additionalData['CompanyName']);
                $this->getInfoInstance()->setAdditionalInformation(
                    'selectedBusiness',
                    $additionalData['selectedBusiness']
                );
            }

            if (isset($additionalData['customer_telephone'])) {
                $this->getInfoInstance()->setAdditionalInformation(
                    'customer_telephone',
                    $additionalData['customer_telephone']
                );
            }
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     * @throws \TIG\Buckaroo\Exception
     */
    public function getKlarnaRequestParameters($payment)
    {
        // First data to set is the billing address data.
        $requestData = $this->getRequestBillingData($payment);

        // Merge the shipping data
        $requestData = array_merge($requestData, $this->getRequestShippingData($payment));

        // Merge the article data; products and fee's
        $requestData = array_merge($requestData, $this->getRequestArticlesData($requestData, $payment));

        return $requestData;
    }


    /**
     * Method to compare two addresses from the payment.
     * Returns true if they are the same.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return boolean
     */
    public function isAddressDataDifferent($payment)
    {
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $shippingAddress = $payment->getOrder()->getShippingAddress();

        if ($billingAddress === null || $shippingAddress === null) {
            return false;
        }

        $billingAddressData = $billingAddress->getData();
        $shippingAddressData = $shippingAddress->getData();

        $arrayDifferences = $this->calculateAddressDataDifference($billingAddressData, $shippingAddressData);

        if (empty($arrayDifferences)) {
            return "true";
        }

        return "false";
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return array
     */
    public function getRequestBillingData($payment)
    {
        /**
         * @var \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress
         */
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $streetFormat = $this->formatStreet($billingAddress->getStreet());

        $listCountries = Zend_Locale::getTranslationList('territory', 'en_US');

        $telephone = $payment->getAdditionalInformation('customer_telephone');
        $telephone = (empty($telephone) ? $billingAddress->getTelephone() : $telephone);

        $birthDayStamp = str_replace('-', '', $payment->getAdditionalInformation('customer_DoB'));
        $billingData = [
            [
                '_' => $telephone,
                'Name' => 'BillingCellPhoneNumber',
            ],
            [
                '_' => $billingAddress->getCity(),
                'Name' => 'BillingCity',
            ],
            [
                '_' => $billingAddress->getCountryId(),
                'Name' => 'BillingCountry',
            ],
            [
                '_' => $billingAddress->getEmail(),
                'Name' => 'BillingEmail',
            ],
            [
                '_' => $billingAddress->getFirstname(),
                'Name' => 'BillingFirstName',
            ],
            [
                '_' => $billingAddress->getLastName(),
                'Name' => 'BillingLastName',
            ],
            [
                '_' => $telephone,
                'Name' => 'BillingPhoneNumber',
            ],
            [
                '_' => $billingAddress->getPostcode(),
                'Name' => 'BillingPostalCode',
            ],
            [
                '_' => $streetFormat['street'],
                'Name' => 'BillingStreet',
            ],
            [
                '_' => $payment->getAdditionalInformation('customer_gender'),
                'Name' => 'Gender',
            ],
            [
                '_' => $billingAddress->getCountryId(),
                'Name' => 'OperatingCountry',
            ],
            [
                '_' => $birthDayStamp,
                'Name' => 'Pno',
            ],
            [
                '_' => $listCountries[$billingAddress->getCountryId()],
                'Name' => 'Encoding',
            ]
        ];

        if (!empty($streetFormat['house_number'])) {
            $billingData[] = [
                '_'    => $streetFormat['house_number'],
                'Name' => 'BillingHouseNumber',
            ];
        }

        if (!empty($streetFormat['number_addition'])) {
            $billingData[] = [
                '_'    => $streetFormat['number_addition'],
                'Name' => 'BillingHouseNumberSuffix',
            ];
        }

        return $billingData;
    }

    /**
     *
     * @return array
     */
    public function getRequestCustomerData()
    {
        $customerData = [
            [
                '_' => $this->getRemoteAddress(),
                'Name' => 'ClientIP',
            ]
        ];

        return $customerData;
    }

    /**
     * @param $street
     *
     * @return array
     */
    public function formatStreet($street)
    {
        $street = implode(' ', $street);

        $format = [
            'house_number' => '',
            'number_addition' => '',
            'street' => $street
        ];

        if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['street'] = trim($matches[3]);
            } else {
                $format['street'] = trim($matches[1]);
                $format['house_number'] = trim($matches[2]);
                $format['number_addition'] = trim($matches[3]);
            }
        }

        return $format;
    }

    /**
     * @param array $addressOne
     * @param array $addressTwo
     *
     * @return boolean
     */
    private function calculateAddressDataDifference($addressOne, $addressTwo)
    {
        $keysToExclude = array_flip([
            'prefix',
            'telephone',
            'fax',
            'created_at',
            'email',
            'customer_address_id',
            'vat_request_success',
            'vat_request_date',
            'vat_request_id',
            'vat_is_valid',
            'vat_id',
            'address_type',
            'extension_attributes',
        ]);

        $filteredAddressOne = array_diff_key($addressOne, $keysToExclude);
        $filteredAddressTwo = array_diff_key($addressTwo, $keysToExclude);
        $arrayDiff = array_diff($filteredAddressOne, $filteredAddressTwo);

        return $arrayDiff;
    }

    /**
     * @param $requestData
     * @param $payment
     *
     * @return array
     */
    public function getRequestArticlesData($requestData, $payment)
    {
        $includesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_INCLUDES_TAX);

        /**
         * @var \Magento\Eav\Model\Entity\Collection\AbstractCollection|array $cartData
         */

        $cartData = $this->cart->getItems();

        $articles = array();
        $group    = 1;
        $max      = 99;
        $i        = 1;

        foreach ($cartData as $item) {
            if (empty($item) || $item->hasParentItemId()) {
                continue;
            }

            $article = [
                [
                    '_' => $item->getName(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleTitle',
                ],
                [
                    '_' => $item->getSku(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleNumber',
                ],
                [
                    '_' => self::KLARNA_ARTICLE_TYPE_GENERAL,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleType',
                ],
                [
                    '_' => $item->getBasePriceInclTax(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticlePrice',
                ],
                [
                    '_' => $item->getQty(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleQuantity',
                ],
                [
                    '_' => $item->getTaxPercent(),
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleVat',
                ]
            ];

            $articles = array_merge($articles, $article);
            $group++;

            if ($i > $max) {
                break;
            }
        }

        $serviceLine = $this->getServiceCostLine($payment->getOrder(), $includesTax, $group);

        if (!empty($serviceLine)) {
            $requestData = array_merge($articles, $serviceLine);
            $group++;
        } else {
            $requestData = $articles;
        }

        // Add aditional shippin costs.
        $shippingCosts = $this->getShippingCostsLine($payment->getOrder(), $group);

        if (!empty($shippingCosts)) {
            $requestData = array_merge($requestData, $shippingCosts);
            $group++;
        }

        $discountline = $this->getDiscountLine($payment, $group);

        if (!empty($discountline)) {
            $requestData = array_merge($requestData, $discountline);
            $group++;
        }

        return $requestData;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $productItem
     * @param                                 $includesTax
     *
     * @return mixed
     */
    public function calculateProductPrice($productItem, $includesTax)
    {
        if ($includesTax) {
            $productPrice = $productItem->getRowTotalInclTax();
        } else {
            $productPrice = $productItem->getRowTotal();
        }

        return $productPrice;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return float|int
     */
    private function getDiscountAmount($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $discount = 0;
        $edition = $this->softwareData->getProductMetaData()->getEdition();

        if ($order->getDiscountAmount() < 0) {
            $discount -= abs((double)$order->getDiscountAmount());
        }

        if ($edition == 'Enterprise' && $order->getCustomerBalanceAmount() > 0) {
            $discount -= abs((double)$order->getCustomerBalanceAmount());
        }

        return $discount;
    }

    /**
     * Get the discount cost lines
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     * @param $group
     *
     * @return array
     */
    public function getDiscountLine($payment, $group)
    {
        $article = [];
        $discount = $this->getDiscountAmount($payment);

        if ($discount >= 0) {
            return $article;
        }

        $article = [
            [
                '_' => 3,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleNumber',
            ],
            [
                '_' => $discount,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticlePrice',
            ],
            [
                '_' => 1,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleQuantity',
            ],
            [
                '_' => 'Discount',
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleTitle',
            ],
            [
                '_' => 0,
                'Group' => 'Article',
                'GroupID' => $group,
                'Name' => 'ArticleVat',
            ],
        ];

        return $article;
    }

    /**
     * @param OrderInterface $order
     * @param $group
     *
     * @return array
     */
    private function getShippingCostsLine($order, $group)
    {
        $shippingCostsArticle = [];

        if ($order->getShippingAmount() <= 0) {
            return $shippingCostsArticle;
        }


        $request = $this->taxCalculation->getRateRequest(null, null, null);
        $taxClassId = $this->taxConfig->getShippingTaxClass();
        $percent = $this->taxCalculation->getRate($request->setProductClassId($taxClassId));

        $shippingIncludesTax = $this->_scopeConfig->getValue(static::TAX_CALCULATION_SHIPPING_INCLUDES_TAX);
        $shippingAmount = $order->getShippingAmount();

        if ($shippingIncludesTax) {
            $shippingAmount += $order->getShippingTaxAmount();
        }

            $shippingCostsArticle = [
                [
                    '_' => 2,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleNumber',
                ],
                [
                    '_' => $shippingAmount,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticlePrice',
                ],
                [
                    '_' => 1,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleQuantity',
                ],
                [
                    '_' => 'Verzendkosten',
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleTitle',
                ],
                [
                    '_' => $percent,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleVat',
                ],
                [
                    '_' => self::KLARNA_ARTICLE_TYPE_SHIPMENTFEE,
                    'Group' => 'Article',
                    'GroupID' => $group,
                    'Name' => 'ArticleType',
                ]
            ];

            return $shippingCostsArticle;
        }

    /**
     * Get the service cost lines (buckfee)
     *
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Invoice|\Magento\Sales\Model\Order\Creditmemo $order
     * @param $includesTax
     *
     * @param $group
     * @return   array
     * @internal param $ (int) $latestKey
     */
    public function getServiceCostLine($order, $includesTax, $group)
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $buckarooFee = $order->getBuckarooFee();
        $buckarooTax = $order->getBuckarooFeeTaxAmount();

        $items = $order->getItems();

        foreach ($items as $data) {

            $article = [];

            if (false !== $buckarooFee && (double)$buckarooFee > 0) {
                $article = [
                    [
                        '_' => 1,
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleNumber',
                    ],
                    [
                        '_' => round($buckarooFee + $buckarooTax, 2),
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticlePrice',
                    ],
                    [
                        '_' => 1,
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleQuantity',
                    ],
                    [
                        '_' => 'Servicekosten',
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleTitle',
                    ],
                    [
                        '_' => $this->getTaxPercent($data),
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleVat',
                    ],
                    [
                        '_' => self::KLARNA_ARTICLE_TYPE_HANDLINGFEE,
                        'Group' => 'Article',
                        'GroupID' => $group,
                        'Name' => 'ArticleType',
                    ]
                ];
            }

            return $article;
        }
    }

    /**
     * @param $data
     * @return string
     */
    private function getTaxPercent($data)
    {
        $taxPercent = $data->getTaxPercent();
        if (!$taxPercent) {
            $taxPercent = $data->getOrderItem()->getTaxPercent();
        }

        return $taxPercent;
    }
}
