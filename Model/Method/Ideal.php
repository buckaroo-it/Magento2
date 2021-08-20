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

use Magento\Sales\Model\Order\Payment;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Magento\Quote\Model\Quote\AddressFactory;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Registry\BuckarooRegistry as BuckarooRegistry;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal as IdealConfig;

class Ideal extends AbstractMethod
{
    /**
     * Payment Code
     */
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_ideal';

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode = 'ideal';

    // @codingStandardsIgnoreStart
    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    // @codingStandardsIgnoreEnd

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        BuckarooRegistry $buckarooRegistry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Developer\Helper\Data $developmentHelper,
        \Buckaroo\Magento2\Service\CreditManagement\ServiceParameters $serviceParameters,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        BuckarooLog $buckarooLog,
        SoftwareData $softwareData,
        AddressFactory $addressFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway = null,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory = null,
        \Buckaroo\Magento2\Model\ValidatorFactory $validatorFactory = null,
        \Buckaroo\Magento2\Helper\Data $helper = null,
        \Buckaroo\Magento2\Helper\PaymentGroupTransaction $paymentGroupTransactionHelper,
        \Magento\Framework\App\RequestInterface $request = null,
        \Buckaroo\Magento2\Model\RefundFieldsFactory $refundFieldsFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory = null,
        \Magento\Framework\HTTP\Client\Curl $curl,
        IdealConfig $idealConfig,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $buckarooRegistry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $developmentHelper,
            $quoteFactory,
            $buckarooLog,
            $softwareData,
            $addressFactory,
            $resource,
            $resourceCollection,
            $gateway,
            $transactionBuilderFactory,
            $validatorFactory,
            $helper,
            $paymentGroupTransactionHelper,
            $request,
            $refundFieldsFactory,
            $configProviderFactory,
            $configProviderMethodFactory,
            $curl,
            $data
        );

        $this->idealConfig = $idealConfig;
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
            'Name'             => 'ideal',
            'Action'           => $this->getPayRemainder($payment,$transactionBuilder),
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $payment->getAdditionalInformation('issuer'),
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

        /**
         * @var IdealConfig $config
         */
        $config = $this->idealConfig;

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
        foreach ($config->getIssuers() as $issuer) {
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
