<?php

namespace Buckaroo\Magento2\Model\Method;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Developer\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NotFoundException;
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

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooAdapter extends \Magento\Payment\Model\Method\Adapter
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';
    public const BUCKAROO_ALL_TRANSACTIONS = 'buckaroo_all_transactions';
    public const BUCKAROO_PAYMENT_IN_TRANSIT = 'buckaroo_payment_in_transit';
    public const PAYMENT_FROM = 'buckaroo_payment_from';

    /**
     * @var bool
     */
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

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    public $priceHelper;

    /**
     * @var int
     */
    protected $payRemainder = 0;

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param ObjectManagerInterface $objectManager
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param RequestInterface|null $request
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param LoggerInterface|null $logger
     * @param bool $usesRedirect
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ManagerInterface                                           $eventManager,
        ValueHandlerPoolInterface                                  $valueHandlerPool,
        PaymentDataObjectFactory                                   $paymentDataObjectFactory,
                                                                   $code,
                                                                   $formBlockType,
                                                                   $infoBlockType,
        ObjectManagerInterface                                     $objectManager,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory     $configProviderMethodFactory,
        \Magento\Framework\Pricing\Helper\Data                     $priceHelper,
        RequestInterface                                           $request = null,
        CommandPoolInterface                                       $commandPool = null,
        ValidatorPoolInterface                                     $validatorPool = null,
        CommandManagerInterface                                    $commandExecutor = null,
        LoggerInterface                                            $logger = null,
        bool                                                       $usesRedirect = true
    ) {
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
        $this->usesRedirect = $usesRedirect;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceHelper = $priceHelper;
    }

    /**
     * @inheritdoc
     * This is a temporary workaround for https://github.com/magento/magento2/issues/33869.
     * It sets the info instance before the method gets executed. Otherwise, the validator doesn't get called
     * correctly.
     * @throws NotFoundException
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if (null == $quote) {
            return false;
        }

        try {
            $validator = $this->getValidatorPool()->get('buckaroo_availability');
            $result = $validator->validate(
                [
                    'paymentMethodInstance' => $this,
                    'quote' => $quote
                ]
            );
            if (!$result->isValid()) {
                return false;
            }
        } // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        catch (\Exception $e) {
            // pass
        }

        return parent::isAvailable($quote);
    }

    /**
     * @inheritdoc
     */
    public function cancel(InfoInterface $payment)
    {
        if (!self::$requestOnVoid) {
            return $this;
        }

        $this->executeCommand('cancel', ['payment' => $payment]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function void(InfoInterface $payment)
    {
        if (!self::$requestOnVoid) {
            return $this;
        }

        $this->executeCommand('void', ['payment' => $payment]);

        return $this;
    }


    /**
     * @inheritdoc
     */
    public function canUseForCountry($country)
    {
        try {
            $validator = $this->getValidatorPool()->get('country');
        } catch (\Exception $e) {
            return true;
        }

        $result = $validator->validate([
            'methodInstance' => $this,
            'country' => $country,
            'storeId' => $this->getStore()
        ]);

        return $result->isValid();
    }

    /**
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param PushRequestInterface $postData
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
        if ($payment->getMethod() == 'buckaroo_magento2_klarnakp') {
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
        if ($originalTransactionKey !== null) {
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
