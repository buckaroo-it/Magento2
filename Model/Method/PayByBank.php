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

use Buckaroo\Magento2\Gateway\GatewayInterface;
use Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory;
use Buckaroo\Magento2\Helper\Data as HelperData;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as MethodFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayByBank as PayByBankConfig;
use Buckaroo\Magento2\Model\RefundFieldsFactory;
use Buckaroo\Magento2\Model\ValidatorFactory;
use Buckaroo\Magento2\Service\CustomerAttributes;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Developer\Helper\Data as DevelopmentHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentData;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;

class PayByBank extends AbstractMethod
{

    public const EAV_LAST_USED_ISSUER_ID = 'buckaroo_last_paybybank_issuer';

    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_paybybank';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'paybybank';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * @var CustomerAttributes
     */
    protected CustomerAttributes $customerAttributes;

    /**
     * @var PayByBankConfig
     */
    private PayByBankConfig $payByBankConfig;

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
        CustomerAttributes $customerAttributes,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        Config $taxConfig,
        Calculation $taxCalculation,
        \Buckaroo\Magento2\Model\ConfigProvider\BuckarooFee $configProviderBuckarooFee,
        BuckarooLog $buckarooLog,
        SoftwareData $softwareData,
        AddressFactory $addressFactory,
        EventManager $eventManager,
        PayByBankConfig $payByBankConfig,
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
        $this->customerAttributes = $customerAttributes;
        $this->payByBankConfig = $payByBankConfig;
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
        $this->saveLastUsedIssuer($payment);
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $serviceName = 'paybybank';
        $issuer = $payment->getAdditionalInformation('issuer');
        if ($issuer === 'INGBNL2A' && $this->helper->isMobile()) {
            $serviceName = 'ideal';
        }

        $services = [
            'Name'             => $serviceName,
            'Action'           => $this->getPayRemainder($payment, $transactionBuilder),
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $issuer,
                    'Name' => 'issuer',
                ],
            ],
        ];

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return void
     * @throws LocalizedException
     */
    public function saveLastUsedIssuer($payment)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $customerId = $order->getCustomerId();

        if ($customerId !== null) {
            $this->customerAttributes->setAttribute(
                $customerId,
                self::EAV_LAST_USED_ISSUER_ID,
                $this->getInfoInstance()->getAdditionalInformation('issuer')
            );
        }
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
    public function getVoidTransactionBuilder($payment)
    {
        return true;
    }

    /**
     * Validate that we received a valid issuer ID.
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();

        $skipValidation = $paymentInfo->getAdditionalInformation('buckaroo_skip_validation');
        if ($skipValidation) {
            return $this;
        }

        $chosenIssuer = $paymentInfo->getAdditionalInformation('issuer');

        if (!$chosenIssuer) {
            if ($content = $this->request->getContent()) {
                $jsonDecode = $this->helper->getJson()->unserialize($content);
                if (!empty($jsonDecode['paymentMethod']['additional_data']['issuer'])) {
                    $chosenIssuer = $jsonDecode['paymentMethod']['additional_data']['issuer'];
                    $this->getInfoInstance()->setAdditionalInformation('issuer', $chosenIssuer);
                }
            }
        }

        $valid = false;
        foreach ($this->payByBankConfig->getIssuers() as $issuer) {
            if ($issuer['code'] == $chosenIssuer) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please select a issuer from the list'));
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return $this->buckarooPaymentMethodCode;
    }
}
