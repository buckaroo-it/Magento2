<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\Registry;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentDetailsHandler implements HandlerInterface
{
    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * Constructor
     *
     * @param Data $helper
     */
    public function __construct(
        Data     $helper,
        Registry $registry
    ) {
        $this->helper = $helper;
        $this->registry = $registry;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();

        /** @var TransactionResponse $transaction */
        $transactionResponse = SubjectReader::readTransactionResponse($response);
        $arrayResponse = $transactionResponse->toArray();

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        $rawInfo = $this->getTransactionAdditionalInfo($arrayResponse);

        $payment->setTransactionAdditionalInfo(
            \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
            \json_encode($rawInfo)
        );

        // SET REGISTRY BUCKAROO REDIRECT
        $this->addToRegistry('buckaroo_response', $arrayResponse);
    }

    /**
     * Get array of transaction Additional Info
     *
     * @param array $array
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array)
    {
        return $this->helper->getTransactionAdditionalInfo($array);
    }

    /**
     * Add Buckaroo Response in Registry
     *
     * @param string $key
     * @param array $value
     */
    protected function addToRegistry(string $key, $value)
    {
        // if the key doesn't exist or is empty, the data can be directly added and registered
        if (!$this->registry->registry($key)) {
            $this->registry->register($key, [$value]);
            return;
        }

        $registryValue = $this->registry->registry($key);
        $registryValue[] = $value;

        $this->registry->unregister($key);
        $this->registry->register($key, $registryValue);
    }
}
