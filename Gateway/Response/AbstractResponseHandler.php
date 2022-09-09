<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;

class AbstractResponseHandler
{
    const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';
    const BUCKAROO_PAYMENT_IN_TRANSIT = 'buckaroo_payment_in_transit';
    /**
     * @var Registry
     */
    protected Registry $registry;
    /**
     * @var Data
     */
    protected Data $helper;
    /**
     * @var TransactionResponse
     */
    protected TransactionResponse $transactionResponse;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $eventManager;
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;
    /**
     * @var BuckarooLog
     */
    protected BuckarooLog $buckarooLog;
    /**
     * @var MessageManager
     */
    protected MessageManager $messageManager;


    public function __construct(
        Registry           $registry,
        Data               $helper,
        ManagerInterface   $eventManager,
        BuckarooLog        $buckarooLog,
        ResourceConnection $resourceConnection,
        MessageManager     $messageManager
    )
    {
        $this->registry = $registry;
        $this->helper = $helper;
        $this->eventManager = $eventManager;
        $this->buckarooLog = $buckarooLog;
        $this->resourceConnection = $resourceConnection;
        $this->messageManager = $messageManager;
    }

    /**
     * @param TransactionResponse $response
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param                                                                                    $close
     * @param bool $saveId
     *
     * @return OrderPaymentInterface|InfoInterface
     * @throws LocalizedException
     */
    public function saveTransactionData(
        TransactionResponse $response,
        InfoInterface       $payment,
                            $close,
                            $saveId = false
    )
    {
        if (!empty($response->getTransactionKey())) {
            $transactionKey = $response->getTransactionKey();

            $payment->setIsTransactionClosed($close);

            /**
             * Recursively convert object to array.
             */
            $arrayResponse = $this->transactionResponse->toArray();

            /**
             * Save the transaction's response as additional info for the transaction.
             */
            $rawInfo = $this->getTransactionAdditionalInfo($arrayResponse);

            $payment->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                $rawInfo
            );

            $payment->getMethodInstance()->processCustomPostData($payment, $arrayResponse);

            $payment->setTransactionId($transactionKey);

            $this->setPaymentInTransit($payment);
            /**
             * Save the payment's transaction key.
             */
            if ($saveId) {
                $payment->setAdditionalInformation(self::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY, $transactionKey);
            }

            $skipFirstPush = $payment->getAdditionalInformation('skip_push');
            /**
             * Buckaroo Push is send before Response, for correct flow we skip the first push
             * for some payment methods
             * @todo when buckaroo changes the push / response order this can be removed
             */
            if ($skipFirstPush > 0) {
                $payment->setAdditionalInformation('skip_push', $skipFirstPush - 1);
                if (!empty($payment->getOrder()) && !empty($payment->getOrder()->getId())) {
                    // Only save payment if order is already saved, this to avoid foreign key constraint error
                    // on table sales_order_payment, column parent_id.
                    $payment->save();
                }
            }
        }

        return $payment;
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array)
    {
        return $this->helper->getTransactionAdditionalInfo($array);
    }

    /**
     * Set flag if user is on the payment provider page
     *
     * @param InfoInterface $payment
     * @param bool $inTransit
     * @return void
     */
    public function setPaymentInTransit(InfoInterface $payment, $inTransit = true)
    {
        $payment->setAdditionalInformation(self::BUCKAROO_PAYMENT_IN_TRANSIT, $inTransit);
    }

    /**
     * @param $name
     * @param $payment
     * @param $response
     *
     * @return $this
     */
    protected function dispatchAfterEvent($name, $payment, $response)
    {
        $this->eventManager->dispatch(
            $name,
            [
                'payment' => $payment,
                'response' => $response,
            ]
        );

        return $this;
    }

    /**
     * @param string $key
     * @param        $value
     */
    protected function addToRegistry(string $key, $value)
    {
        // if the key doesn't exist or is empty, the data can be directly added and registered
        if (!$this->registry->registry($key)) {
            $this->registry->register($key, [$value]);
            return;
        }

        $registryValue   = $this->registry->registry($key);
        $registryValue[] = $value;

        $this->registry->unregister($key);
        $this->registry->register($key, $registryValue);
    }
}
