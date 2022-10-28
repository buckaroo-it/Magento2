<?php

namespace Buckaroo\Magento2\Model\Method;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;

class BuckarooAdapter extends \Magento\Payment\Model\Method\Adapter
{
    public static bool $requestOnVoid = true;
    /**
     * @var bool
     */
    public bool $usesRedirect = true;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    private State $state;

    /**
     * @var \Magento\Developer\Helper\Data
     */
    protected $developmentHelper;

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

    protected $payRemainder = 0;

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param ObjectManagerInterface $objectManager
     * @param State $state
     * @param \Magento\Developer\Helper\Data $developmentHelper
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param RequestInterface|null $request
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param LoggerInterface|null $logger
     * @param bool $usesRedirect
     */
    public function __construct(
        ManagerInterface                                       $eventManager,
        ValueHandlerPoolInterface                              $valueHandlerPool,
        PaymentDataObjectFactory                               $paymentDataObjectFactory,
                                                               $code,
                                                               $formBlockType,
                                                               $infoBlockType,
        ObjectManagerInterface                                 $objectManager,
        State                                                  $state,
        \Magento\Developer\Helper\Data                         $developmentHelper,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory        $configProviderFactory,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory,
        \Magento\Framework\Pricing\Helper\Data                 $priceHelper,
        RequestInterface                                       $request = null,
        CommandPoolInterface                                   $commandPool = null,
        ValidatorPoolInterface                                 $validatorPool = null,
        CommandManagerInterface                                $commandExecutor = null,
        LoggerInterface                                        $logger = null,
        bool                                                   $usesRedirect = true
    )
    {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
            $logger
        );

        $this->buckarooPaymentMethodCode = $this->setBuckarooPaymentMethodCode();
        $this->objectManager = $objectManager;
        $this->request = $request;
        $this->state = $state;
        $this->developmentHelper = $developmentHelper;
        $this->usesRedirect = $usesRedirect;
        $this->configProviderFactory = $configProviderFactory;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceHelper = $priceHelper;
    }

    /**
     * @inheritdoc
     * This is a temporary workaround for https://github.com/magento/magento2/issues/33869.
     * It sets the info instance before the method gets executed. Otherwise, the validator doesn't get called
     * correctly.
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if (null == $quote) {
            return false;
        }
        /**
         * @var Account $accountConfig
         */
        $accountConfig = $this->configProviderFactory->get('account');
        if ($accountConfig->getActive() == 0) {
            return false;
        }

        $areaCode = $this->state->getAreaCode();
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

        $this->setInfoInstance($quote->getPayment());
        return parent::isAvailable($quote);
    }

    /**
     * Check if this payment method is limited by IP.
     *
     * @param Account $accountConfig
     * @param CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnIp(
        Account                               $accountConfig,
        CartInterface $quote = null
    )
    {
        $methodValue = $this->getConfigData('limit_by_ip');
        if ($accountConfig->getLimitByIp() == 1 || $methodValue == 1) {
            $storeId = $quote ? $quote->getStoreId() : null;
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
     * @param CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnAmount(CartInterface $quote = null)
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
     * @param CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnCurrency(CartInterface $quote = null)
    {
        $allowedCurrenciesRaw = $this->getConfigData('allowed_currencies');
        $allowedCurrencies = explode(',', (string)$allowedCurrenciesRaw);

        $currentCurrency = $quote->getCurrency()->getQuoteCurrencyCode();

        return $allowedCurrenciesRaw === null || in_array($currentCurrency, $allowedCurrencies);
    }

    /**s
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param PushRequestInterface $postData
     *
     * @return bool
     */
    public function canProcessPostData($payment, PushRequestInterface $postData)
    {
        return true;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array $postData
     */
    public function processCustomPostData($payment, $postData)
    {
        if($payment->getMethod() == 'buckaroo_magento2_klarnakp') {
            $order = $payment->getOrder();

            if ($order->getBuckarooReservationNumber()) {
                return;
            }

            if (isset($postData['brq_service_klarnakp_reservationnumber'])) {
                $order->setBuckarooReservationNumber($postData['brq_service_klarnakp_reservationnumber']);
                $order->save();
            }
        }
    }

    /**
     * @param \Magento\Framework\DataObject $data
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

    protected function getPayRemainder($payment, $transactionBuilder, $serviceAction = 'Pay', $newServiceAction = 'PayRemainder')
    {
        /** @var \Buckaroo\Magento2\Helper\PaymentGroupTransaction */
        $paymentGroupTransaction = $this->objectManager->create('\Buckaroo\Magento2\Helper\PaymentGroupTransaction');
        $incrementId = $payment->getOrder()->getIncrementId();

        $originalTransactionKey = $paymentGroupTransaction->getGroupTransactionOriginalTransactionKey($incrementId);
        if ($originalTransactionKey !== false) {
            $serviceAction = $newServiceAction;
            $transactionBuilder->setOriginalTransactionKey($originalTransactionKey);

            $alreadyPaid = $paymentGroupTransaction->getAlreadyPaid($incrementId);
            if ($alreadyPaid > 0) {
                $this->payRemainder = $this->getPayRemainderAmount($payment, $alreadyPaid);
                $transactionBuilder->setAmount($this->payRemainder);
            }
        }
        return $serviceAction;
    }

    protected function getPayRemainderAmount($payment, $alreadyPaid)
    {
        return $payment->getOrder()->getGrandTotal() - $alreadyPaid;
    }

    protected function setBuckarooPaymentMethodCode()
    {
        return str_replace('buckaroo_magento2_', '', $this->getCode());
    }

    /**
     * @param PushRequestInterface $responseData
     *
     * @return bool
     */
    public function canPushInvoice(PushRequestInterface $responseData): bool
    {
        if ($this->getConfigData('payment_action') == 'authorize') {
            return false;
        }

        return true;
    }


    /**
     * @return string
     * @throws \Buckaroo\Magento2\Exception
     */
    public function getTitle()
    {
        $title = $this->getConfigData('title');

        if (!$this->configProviderMethodFactory->has($this->buckarooPaymentMethodCode)) {
            return $title;
        }

        $paymentFee = trim($this->configProviderMethodFactory->get($this->buckarooPaymentMethodCode)->getPaymentFee());
        if (!$paymentFee || (float)$paymentFee < 0.01) {
            return $title;
        }

        if (strpos($paymentFee, '%') === false) {
            $title .= ' + ' . $this->priceHelper->currency(number_format($paymentFee, 2), true, false);
        } else {
            $title .= ' + ' . $paymentFee;
        }

        return $title;
    }
}
