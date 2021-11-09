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

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderFactory;

use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Config;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Magento2\Service\Software\Data as SoftwareData;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderModelFactory;

class MethodAdapter extends \Magento\Payment\Model\Method\Adapter
{
    const CODE = 'buckaroo';
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';
    const BUCKAROO_ALL_TRANSACTIONS             = 'buckaroo_all_transactions';

    /**
     * The regex used to validate the entered BIC number
     */
    const BIC_NUMBER_REGEX = '^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$^';

    /**
     * @var bool|string
     */
    public $orderPlaceRedirectUrl = true;


    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Factory
     */
    public $configProviderFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory
     */
    public $configProviderMethodFactory;
    
    /**
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    public $priceHelper;

    /**
     * @var OrderPaymentInterface|InfoInterface
     */
    public $payment;


    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $formBlockType,
        $infoBlockType,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        BuckarooLog $logger = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory  $configProviderMethodFactory = null,
        \Magento\Framework\Pricing\Helper\Data $priceHelper = null
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            static::CODE,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
            $logger
        );

        $this->eventManager = $eventManager;
        $this->configProviderFactory = $configProviderFactory;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (null == $quote) {
            return false;
        }
        /**
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
         */
        $accountConfig = $this->configProviderFactory->get('account');
        if ($accountConfig->getActive() == 0) {
            return false;
        }

        $areaCode = 'adminhtml';//$this->_appState->getAreaCode();
        if ('adminhtml' === $areaCode
            && $this->getConfigData('available_in_backend') !== null
            && $this->getConfigData('available_in_backend') == 0
        ) {
            return false;
        }

        if (!$this->isAvailableBasedOnIp($accountConfig, $quote)) {
            return false;
        }

        if (!$this->isAvailableBasedOnAmount($quote)) {
            return false;
        }

        if (!$this->isAvailableBasedOnCurrency($quote)) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Check if this payment method is limited by IP.
     *
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
     * @param \Magento\Quote\Api\Data\CartInterface      $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnIp(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        $methodValue = $this->getConfigData('limit_by_ip');
        if ($accountConfig->getLimitByIp() == 1 || $methodValue == 1) {
            $storeId   = $quote ? $quote->getStoreId() : null;
            $isAllowed = $this->developmentHelper->isDevAllowed($storeId);

            if (!$isAllowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the grand total exceeds the maximum allowed total.
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnAmount(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $storeId = $quote->getStoreId();
        $maximum = $this->getConfigData('max_amount', $storeId);
        $minimum = $this->getConfigData('min_amount', $storeId);

        /**
         * @var \Magento\Quote\Model\Quote $quote
         */
        $total = $quote->getGrandTotal();

        if ($total < 0.01) {
            return false;
        }

        if ($maximum !== null && $total > $maximum) {
            return false;
        }

        if ($minimum !== null && $total < $minimum) {
            return false;
        }

        return true;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnCurrency(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $allowedCurrenciesRaw = $this->getConfigData('allowed_currencies');
        $allowedCurrencies    = explode(',', $allowedCurrenciesRaw);

        $currentCurrency = $quote->getCurrency()->getQuoteCurrencyCode();

        return $allowedCurrenciesRaw === null || in_array($currentCurrency, $allowedCurrencies);
    }


    /**
     * @param \Magento\Framework\DataObject $data
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        if ($data instanceof \Magento\Framework\DataObject) {
            $additionalSkip = $data->getAdditionalData();
            $skipValidation = $data->getBuckarooSkipValidation();

            if ($skipValidation === null && isset($additionalSkip['buckaroo_skip_validation'])) {
                $skipValidation = $additionalSkip['buckaroo_skip_validation'];
            }

            $this->getInfoInstance()->setAdditionalInformation('buckaroo_skip_validation', $skipValidation);
        }
        return $this;
    }
    
    /**
     * @param  \Magento\Framework\DataObject $data
     *
     * @return array
     */
    public function assignDataConvertToArray(\Magento\Framework\DataObject $data)
    {
        if (!is_array($data)) {
            $data = $data->convertToArray();
        }

        return $data;
    }

    protected function assignDataCommon(array $data)
    {
        if (isset($data['additional_data']['termsCondition'])) {
            $additionalData = $data['additional_data'];
            $this->getInfoInstance()->setAdditionalInformation('termsCondition', $additionalData['termsCondition']);
            $this->getInfoInstance()->setAdditionalInformation('customer_gender', $additionalData['customer_gender']);
            $this->getInfoInstance()->setAdditionalInformation('customer_billingName', $additionalData['customer_billingName']);
            $this->getInfoInstance()->setAdditionalInformation('customer_identificationNumber', $additionalData['customer_identificationNumber']);

            $dobDate = \DateTime::createFromFormat('d/m/Y', $additionalData['customer_DoB']);
            $dobDate = (!$dobDate ? $additionalData['customer_DoB'] : $dobDate->format('Y-m-d'));
            $this->getInfoInstance()->setAdditionalInformation('customer_DoB', $dobDate);

            if (isset($additionalData['customer_telephone'])) {
                $this->getInfoInstance()->setAdditionalInformation(
                    'customer_telephone',
                    $additionalData['customer_telephone']
                );
            }
        }
    }

    protected function assignDataCommonV2(array $data)
    {
        if (isset($data['additional_data']['customer_gender'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation('customer_gender', $data['additional_data']['customer_gender']);
        }

        if (isset($data['additional_data']['customer_billingFirstName'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation(
                    'customer_billingFirstName',
                    $data['additional_data']['customer_billingFirstName']
                );
        }

        if (isset($data['additional_data']['customer_billingLastName'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation(
                    'customer_billingLastName',
                    $data['additional_data']['customer_billingLastName']
                );
        }

        if (isset($data['additional_data']['customer_email'])) {
            $this->getInfoInstance()
                ->setAdditionalInformation('customer_email', $data['additional_data']['customer_email']);
        }
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array                               $postData
     *
     * @return bool
     */
    public function canProcessPostData($payment, $postData)
    {
        return true;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array                               $postData
     */
    public function processCustomPostData($payment, $postData)
    {
        return;
    }

        /**
     * @param $responseData
     *
     * @return bool
     */
    public function canPushInvoice($responseData)
    {
        if ($this->getConfigData('payment_action') == 'authorize') {
            return false;
        }

        return true;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string                                     $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        return parent::getConfigData($field, $storeId);
    }

    /**
     * @return bool|string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->orderPlaceRedirectUrl;
    }



}

