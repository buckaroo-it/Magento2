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

class BuckarooAdapter extends \Magento\Payment\Model\Method\Adapter
{
    const PAYMENT_FROM = 'buckaroo_payment_from';

    /**
     * @var bool
     */
    public bool $usesRedirect = true;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\RequestInterface $request = null,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        LoggerInterface $logger = null,
        bool $usesRedirect = true
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

        $this->objectManager = $objectManager;
        $this->request = $request;
        $this->usesRedirect = $usesRedirect;
    }

    /**s
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
