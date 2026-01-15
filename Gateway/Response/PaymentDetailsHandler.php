<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Api\Data\BuckarooResponseDataInterface;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentDetailsHandler implements HandlerInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var BuckarooResponseDataInterface
     */
    protected $buckarooResponseData;

    /**
     * Constructor
     *
     * @param Data                          $helper
     * @param BuckarooResponseDataInterface $buckarooResponseData
     */
    public function __construct(
        Data $helper,
        BuckarooResponseDataInterface $buckarooResponseData
    ) {
        $this->helper = $helper;
        $this->buckarooResponseData = $buckarooResponseData;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        // Skip if refund was already completed via group transactions
        if (isset($response['group_transaction_refund_complete'])
            && $response['group_transaction_refund_complete'] === true
        ) {
            return;
        }

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

        // SET BUCKAROO RESPONSE REDIRECT
        $this->buckarooResponseData->setResponse($transactionResponse);
    }

    /**
     * Get array of transaction Additional Info
     *
     * @param array $array
     *
     * @return array
     */
    public function getTransactionAdditionalInfo(array $array): array
    {
        return $this->helper->getTransactionAdditionalInfo($array);
    }
}
