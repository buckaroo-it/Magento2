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
namespace TIG\Buckaroo\Model\Method;

use Magento\Developer\Helper\Data as DevelopmentHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use TIG\Buckaroo\Gateway\GatewayInterface;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Helper\Data as HelperData;
use TIG\Buckaroo\Model\ConfigProvider\Factory;
use TIG\Buckaroo\Model\ConfigProvider\Method\Emandate as EmandateConfig;
use TIG\Buckaroo\Model\ConfigProvider\Method\Factory as MethodFactory;
use TIG\Buckaroo\Model\RefundFieldsFactory;
use TIG\Buckaroo\Model\ValidatorFactory;

class Emandate extends AbstractMethod
{
    /** Payment Code */
    const PAYMENT_METHOD_CODE = 'tig_buckaroo_emandate';

    /** @var string */
    public $buckarooPaymentMethodCode = 'emandate';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /** @var bool */
    protected $_isGateway               = true;

    /** @var bool */
    protected $_canOrder                = true;

    /** @var bool */
    protected $_canAuthorize            = false;

    /** @var bool */
    protected $_canCapture              = false;

    /** @var bool */
    protected $_canCapturePartial       = false;

    /** @var bool */
    protected $_canRefund               = false;

    /** @var bool */
    protected $_canVoid                 = true;

    /** @var bool */
    protected $_canUseInternal          = true;

    /** @var bool */
    protected $_canUseCheckout          = true;

    /** @var bool */
    protected $_canRefundInvoicePartial = false;
    // @codingStandardsIgnoreEnd

    /** @var EmandateConfig */
    private $emandateConfig;

    public function __construct(
        ObjectManagerInterface $objectManager,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentData $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        DevelopmentHelper $developmentHelper,
        EmandateConfig $emandateConfig,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        GatewayInterface $gateway = null,
        TransactionBuilderFactory $transactionBuilderFactory = null,
        ValidatorFactory $validatorFactory = null,
        HelperData $helper = null,
        RequestInterface $request = null,
        RefundFieldsFactory $refundFieldsFactory = null,
        Factory $configProviderFactory = null,
        MethodFactory $configProviderMethodFactory = null,
        PriceHelper $priceHelper = null,
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

        $this->emandateConfig = $emandateConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        $data = $this->assignDataConvertToArray($data);

        if (isset($data['additional_data']['buckaroo_skip_validation'])) {
            $this->getInfoInstance()->setAdditionalInformation(
                'buckaroo_skip_validation',
                $data['additional_data']['buckaroo_skip_validation']
            );
        }

        if (isset($data['additional_data']['issuer'])) {
            $this->getInfoInstance()->setAdditionalInformation('issuer', $data['additional_data']['issuer']);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'emandate',
            'Action'           => 'CreateMandate',
            'Version'          => 1,
            'RequestParameter' => $this->getCreateMandateParameters($payment),
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('DataRequest');

        return $transactionBuilder;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     *
     * @return array
     */
    private function getCreateMandateParameters($payment)
    {
        /** @var Order $order */
        $order = $payment->getOrder();
        $billingAddress = $order->getBillingAddress();

        $sequenceType = $this->emandateConfig->getSequenceType();
        $language = $this->emandateConfig->getLanguage();
        $reason = $this->emandateConfig->getReason();

        $parameters = [
            $this->getParameterLine('debtorbankid', $payment->getAdditionalInformation('issuer')),
            $this->getParameterLine('debtorreference', $billingAddress->getEmail()),
            $this->getParameterLine('sequencetype', $sequenceType),
            $this->getParameterLine('purchaseid', $order->getIncrementId()),
            $this->getParameterLine('language', $language)
        ];

        if (!empty($reason)) {
            $parameters[] = $this->getParameterLine('emandatereason', $reason);
        }

        return $parameters;
    }

    /**
     * @param $name
     * @param $value
     *
     * @return array
     */
    private function getParameterLine($name, $value)
    {
        $line = [
            '_'    => $value,
            'Name' => $name,
        ];

        return $line;
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

    /**
     * {@inheritdoc}
     */
    public function getRefundTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }

    /**
     * Validate that we received a valid issuer ID.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();
        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');

        if ($skipValidation) {
            return $this;
        }

        $availableIssuers = $this->emandateConfig->getIssuers();
        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');
        $valid = false;

        foreach ($availableIssuers as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new LocalizedException(__('Please select a issuer from the list'));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function processCustomPostData($payment, $postData)
    {
        parent::processCustomPostData($payment, $postData);

        if (!is_array($postData)) {
            return;
        }

        $fieldsToSave = [
            'MandateId', 'IsError', 'EmandateStatus', 'SignerName', 'AccountName', 'BankId',
            'Iban', 'Reference', 'ValidationReference', 'OriginalMandateId', 'MaxAmount'
        ];

        $filteredData = [];

        array_walk(
            $fieldsToSave,
            function ($field) use (&$filteredData, $postData) {
                $postFieldName = 'brq_service_emandate_' . strtolower($field);

                if (isset($postData[$postFieldName]) && !empty($postData[$postFieldName])) {
                    $filteredData[$field] = $postData[$postFieldName];
                }
            }
        );

        if (count($filteredData) == count($fieldsToSave)) {
            $jsonData = json_encode($filteredData);

            /** @var Order $order */
            $order = $payment->getOrder();
            $order->setBuckarooPushData($jsonData);
            $order->save();
        }
    }
}
