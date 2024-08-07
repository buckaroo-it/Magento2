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

namespace Buckaroo\Magento2\Model\Method;

use Magento\Developer\Helper\Data as DeveloperHelperData;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelperData;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelperData;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Address;
use Buckaroo\Magento2\Gateway\GatewayInterface;
use Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory;
use Buckaroo\Magento2\Helper\Data as BuckarooHelperData;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderMethodFactory;
use Buckaroo\Magento2\Model\RefundFieldsFactory;
use Buckaroo\Magento2\Model\ValidatorFactory;
use Buckaroo\Magento2\Service\Formatter\AddressFormatter;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\Method\Capayable\In3V3Builder;
use Magento\Framework\Event\ManagerInterface as EventManager;

class Capayable extends AbstractMethod
{
    public const DEFAULT_NAME = 'iDEAL In3';
    public const V2_NAME = 'In3';
    
    /** Payment Code */
    const PAYMENT_METHOD_CODE = '';

    const CAPAYABLE_ORDER_SERVICE_ACTION = '';

    /** @var string */
    public $buckarooPaymentMethodCode = '';

    /** @var string */
    protected $_code = '';

    /** @var bool */
    protected $_canUseInternal          = false;

    /** @var AddressFormatter */
    public $addressFormatter;

    /** @var SoftwareData */
    public $softwareData;


    protected $v3Builder;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentHelperData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        DeveloperHelperData $developmentHelper,
        AddressFormatter $addressFormatter,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        Config $taxConfig,
        Calculation $taxCalculation,
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        BuckarooLog $buckarooLog,
        SoftwareData $softwareData,
        AddressFactory $addressFactory,
        EventManager $eventManager,
        In3V3Builder $v3Builder,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        GatewayInterface $gateway = null,
        TransactionBuilderFactory $transactionBuilderFactory = null,
        ValidatorFactory $validatorFactory = null,
        BuckarooHelperData $helper = null,
        RequestInterface $request = null,
        RefundFieldsFactory $refundFieldsFactory = null,
        ConfigProviderFactory $configProviderFactory = null,
        ConfigProviderMethodFactory $configProviderMethodFactory = null,
        PricingHelperData $priceHelper = null,
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
            $quoteFactory,
            $taxConfig,
            $taxCalculation,
            $configProviderBuckarooFee,
            $buckarooLog,
            $softwareData,
            $addressFactory,
            $eventManager,
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

        $this->addressFormatter = $addressFormatter;
        $this->v3Builder = $v3Builder;
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(DataObject $data)
    {
        parent::assignData($data);

        $data = $this->assignDataConvertToArray($data);
        $additionalData = $data['additional_data'];

        $this->assignCapayableData($additionalData);

        return $this;
    }

    /**
     * @param $data
     *
     * @throws LocalizedException
     */
    private function assignCapayableData($data)
    {
        if (isset($data['customer_DoB'])) {
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $this->formatDob($data['customer_DoB']));
        }

        if (isset($data['customer_telephone'])) {
            $this->getInfoInstance()->setAdditionalInformation('customer_telephone', $data['customer_telephone']);
        }
    }


    /**
     * @param string $dob
     *
     * @return bool|\DateTime|string
     */
    private function formatDob($dob)
    {
        $formattedDob = \DateTime::createFromFormat('d/m/Y', $dob);
        $formattedDob = (!$formattedDob ? $dob : $formattedDob->format('Y-m-d'));

        return $formattedDob;
    }

    /**
     * @param string          $value
     * @param string          $name
     * @param null|string     $groupType
     * @param null|string|int $groupId
     *
     * @return array
     */
    private function getRequestParameterRow($value, $name, $groupType = null, $groupId = null)
    {
        $row = [
            '_' => $value,
            'Name' => $name
        ];

        if ($groupType !== null) {
            $row['Group'] = $groupType;
        }

        if ($groupId !== null) {
            $row['GroupID'] = $groupId;
        }

        return $row;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');
        $services = $this->getCapayableService($payment);

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getCapayableService($payment)
    {

        if (!$this->isV2()) {
            $payment->setAdditionalInformation("buckaroo_in3_v3", true);
            return $this->v3Builder->build($payment);
        }

        $requestParameter = [];
        $requestParameter = array_merge($requestParameter, $this->getCustomerData($payment));
        $requestParameter = array_merge($requestParameter, $this->getProductLineData($payment));
        $requestParameter = array_merge($requestParameter, $this->getSubtotalLineData($payment));

        $services = [
            'Name'             => 'capayable',
            'Action'           => static::CAPAYABLE_ORDER_SERVICE_ACTION,
            'RequestParameter' => $requestParameter
        ];

        return $services;
    }

    protected function isV2() {
        return $this->configProviderMethodFactory->get('capayablein3')->isV2();
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     * @throws \Exception
     */
    private function getCustomerData($payment)
    {
        /**@var Address $billingAddress */
        $billingAddress = $payment->getOrder()->getBillingAddress();
        $phone = $billingAddress->getTelephone();

        if ($payment->getAdditionalInformation('customer_telephone') !== null) {
            $phone = $payment->getAdditionalInformation('customer_telephone');
        }
        
        $now = new \DateTime();
        $phoneData = $this->addressFormatter->formatTelephone(
            $phone,
            $billingAddress->getCountryId()
        );

        $customerData = [
            $this->getRequestParameterRow('Debtor', 'CustomerType'),
            $this->getRequestParameterRow($now->format('Y-m-d'), 'InvoiceDate'),
            $this->getRequestParameterRow($phoneData['clean'], 'Phone', 'Phone'),
            $this->getRequestParameterRow($billingAddress->getEmail(), 'Email', 'Email')
        ];

        $customerData = array_merge($customerData, $this->getPersonGroupData($payment));
        $customerData = array_merge($customerData, $this->getAddressGroupData($billingAddress));

        return $customerData;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    private function getPersonGroupData($payment)
    {
        /**@var Address $billingAddress */
        $billingAddress = $payment->getOrder()->getBillingAddress();

        $personGroupData = [
            $this->getRequestParameterRow($this->getInitials($billingAddress->getFirstname()), 'Initials', 'Person'),
            $this->getRequestParameterRow($billingAddress->getLastname(), 'LastName', 'Person'),
            $this->getRequestParameterRow('nl-NL', 'Culture', 'Person'),
            $this->getRequestParameterRow($payment->getAdditionalInformation('customer_DoB'), 'BirthDate', 'Person')
        ];

        return $personGroupData;
    }

    /**
     * @param Address $billingAddress
     *
     * @return array
     */
    private function getAddressGroupData($billingAddress)
    {
        $streetData = $this->addressFormatter->formatStreet($billingAddress->getStreet());

        $addressGroupData = [
            $this->getRequestParameterRow($streetData['street'], 'Street', 'Address'),
            $this->getRequestParameterRow($streetData['house_number'], 'HouseNumber', 'Address'),
            $this->getRequestParameterRow($billingAddress->getPostcode(), 'ZipCode', 'Address'),
            $this->getRequestParameterRow($billingAddress->getCity(), 'City', 'Address'),
            $this->getRequestParameterRow($billingAddress->getCountryId(), 'Country', 'Address')
        ];

        if (strlen($streetData['number_addition']) > 0) {
            $param = $this->getRequestParameterRow($streetData['number_addition'], 'HouseNumberSuffix', 'Address');
            $addressGroupData[] = $param;
        }

        return $addressGroupData;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    private function getProductLineData($payment)
    {
        /** @var \Magento\Sales\Api\Data\OrderItemInterface[] $orderItems */
        $orderItems = $payment->getOrder()->getAllItems();
        $productData = [];
        $max = 99;
        $i = 1;

        foreach ($orderItems as $item) {
            if (empty($item) || $item->hasParentItem()) {
                continue;
            }

            $productData[] = $this->getRequestParameterRow($item->getSku(), 'Code', 'ProductLine', $i);
            $productData[] = $this->getRequestParameterRow($item->getName(), 'Name', 'ProductLine', $i);
            $productData[] = $this->getRequestParameterRow($item->getQtyOrdered(), 'Quantity', 'ProductLine', $i);
            $productData[] = $this->getRequestParameterRow($item->getBasePriceInclTax(), 'Price', 'ProductLine', $i);

            $i++;

            if ($i > $max) {
                break;
            }
        }

        return $productData;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    private function getSubtotalLineData($payment)
    {
        $groupId = 1;
        $subtotalLine = [];
        $order = $payment->getOrder();

        $discountLine = $this->getDiscountLine($order, $groupId);
        if (!empty($discountLine)) {
            $subtotalLine = array_merge($subtotalLine, $discountLine);
            $groupId++;
        }

        $feeLine = $this->getFeeLine($order, $groupId);
        if (!empty($feeLine)) {
            $subtotalLine = array_merge($subtotalLine, $feeLine);
            $groupId++;
        }

        $shippingCostsLine = $this->getShippingCostsLine($order, $groupId);
        if (!empty($shippingCostsLine)) {
            $subtotalLine = array_merge($subtotalLine, $shippingCostsLine);
        }

        return $subtotalLine;
    }

    /**
     * Get text for Discount
     *
     * @return Phrase
     */
    public function getDiscount() : Phrase
    {
        return __('Discount');
    }

    /**
     * @param OrderInterface $order
     * @param int            $groupId
     *
     * @return array
     */
    private function getDiscountLine($order, $groupId)
    {
        $discountLineData = [];
        $discount = abs((double)$order->getDiscountAmount());

        if ($this->softwareData->getProductMetaData()->getEdition() == 'Enterprise') {
            $discount += abs((double)$order->getCustomerBalanceAmount());
        }

        if ($discount <= 0) {
            return $discountLineData;
        }

        $discount = (-1 * round($discount, 2));
        $discountLineData[] = $this->getRequestParameterRow((string)$this->getDiscount(), 'Name', 'SubtotalLine', $groupId);
        $discountLineData[] = $this->getRequestParameterRow($discount, 'Value', 'SubtotalLine', $groupId);

        return $discountLineData;
    }

    /**
     * @param OrderInterface $order
     * @param int            $groupId
     *
     * @return array
     */
    private function getFeeLine($order, $groupId)
    {
        $feeLineData = [];
        $fee = (double)$order->getBuckarooFee();

        if ($fee <= 0) {
            return $feeLineData;
        }

        $feeTax = (double)$order->getBuckarooFeeTaxAmount();
        $feeInclTax = round($fee + $feeTax, 2);
        $feeLineData[] = $this->getRequestParameterRow('Betaaltoeslag', 'Name', 'SubtotalLine', $groupId);
        $feeLineData[] = $this->getRequestParameterRow($feeInclTax, 'Value', 'SubtotalLine', $groupId);

        return $feeLineData;
    }

    /**
     * @param OrderInterface $order
     * @param int            $groupId
     *
     * @return array
     */
    protected function getShippingCostsLine($order, $groupId, &$itemsTotalAmount = 0)
    {
        $shippingCostsArticle = [];

        $shippingAmount = $this->getShippingAmount($order);
        if ($shippingAmount <= 0) {
            return $shippingCostsArticle;
        }

        $shippingCostsArticle[] = $this->getRequestParameterRow('Verzendkosten', 'Name', 'SubtotalLine', $groupId);
        $shippingCostsArticle[] = $this->getRequestParameterRow($shippingAmount, 'Value', 'SubtotalLine', $groupId);

        return $shippingCostsArticle;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getInitials($name)
    {
        $initials = '';
        $nameParts = explode(' ', $name);

        if (empty($nameParts)) {
            return $initials;
        }

        foreach ($nameParts as $part) {
            $initials .= strtoupper(substr($part, 0, 1)) . '.';
        }

        return $initials;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        return false;
    }

    protected function getRefundTransactionBuilderVersion()
    {
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        if ($payment->getAdditionalInformation("buckaroo_in3_v3") === true) {
            return 'In3';
        }

        return 'capayable';
    }

    protected function getRefundTransactionBuilderChannel()
    {
        return '';
    }
}
