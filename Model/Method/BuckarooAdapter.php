<?php

namespace Buckaroo\Magento2\Model\Method;

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
    /**
     * @var bool
     */
    public bool $usesRedirect = true;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
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

    public function __construct(
        ManagerInterface                                $eventManager,
        ValueHandlerPoolInterface                       $valueHandlerPool,
        PaymentDataObjectFactory                        $paymentDataObjectFactory,
                                                        $code,
                                                        $formBlockType,
                                                        $infoBlockType,
        \Magento\Framework\ObjectManagerInterface       $objectManager,
        State                                           $state,
        \Magento\Developer\Helper\Data                  $developmentHelper,
        \Magento\Framework\App\RequestInterface         $request = null,
        CommandPoolInterface                            $commandPool = null,
        ValidatorPoolInterface                          $validatorPool = null,
        CommandManagerInterface                         $commandExecutor = null,
        LoggerInterface                                 $logger = null,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory = null,
        bool                                            $usesRedirect = true
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

        $this->buckarooPaymentMethodCode = $code;
        $this->objectManager = $objectManager;
        $this->request = $request;
        $this->state = $state;
        $this->developmentHelper = $developmentHelper;
        $this->usesRedirect = $usesRedirect;
        $this->configProviderFactory = $configProviderFactory;
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
         * @var \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
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
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return bool
     */
    protected function isAvailableBasedOnIp(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Magento\Quote\Api\Data\CartInterface           $quote = null
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
    protected function canUseForCurrency(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $allowedCurrenciesRaw = $this->getConfigData('allowed_currencies');
        $allowedCurrencies = explode(',', (string)$allowedCurrenciesRaw);

        $currentCurrency = $quote->getCurrency()->getQuoteCurrencyCode();

        return $allowedCurrenciesRaw === null || in_array($currentCurrency, $allowedCurrencies);
    }


    /**s
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array $postData
     *
     * @return bool
     */
    public function canProcessPostData($payment, $postData)
    {
        return true;
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array $postData
     */
    public function processCustomPostData($payment, $postData)
    {
        return;
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
}
