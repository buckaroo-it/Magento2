<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Helper\Data;
use Magento\Framework\App\Request\Http;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Buckaroo\Transaction\Response\TransactionResponse;

class ResponseCodeSDKValidator extends AbstractValidator
{
    /**
     * @var Data $helper
     */
    protected Data $helper;

    /**
     * @var TransactionResponse
     */
    protected TransactionResponse $transaction;

    /**
     * @var Http
     */
    protected Http $request;

    /**
     * @param Data $helper
     * @param Http $request
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        Data $helper,
        Http $request,
        ResultInterfaceFactory $resultFactory
    ) {
        parent::__construct($resultFactory);
        $this->helper = $helper;
        $this->request = $request;
    }

    /**
     * Performs validation of result code
     *
     * @param array $validationSubject
     * @return ResultInterface
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']['object'])) {
            return $this->createResult(
                false,
                [__('Response does not exist')]
            );
        }

        $response = $validationSubject['response']['object'];

        if (!($response instanceof TransactionResponse)) {
            return $this->createResult(
                false,
                [__('Data must be an instance of "TransactionResponse"')]
            );
        }

        $this->transaction = $response;
        $statusCode = $this->getStatusCode();

        switch ($statusCode) {
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD'):
                $success = true;
                break;
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_REJECTED'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER'):
            case $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT'):
                $success = false;
                break;
            default:
                return $this->createResult(
                    false,
                    [__("Invalid Buckaroo status code received: %1.")],
                    [$statusCode]
                );
                //phpcs:ignore:Squiz.PHP.NonExecutableCode
                break;
        }

        if ($success) {
            return $this->createResult(
                true,
                [__('Transaction Success')],
                [$statusCode]
            );
        } else {
            $message = isset($this->transaction->getFirstError()['ErrorMessage']) ?
                $this->transaction->getFirstError()['ErrorMessage']
                : 'Gateway rejected the transaction.';
            return $this->createResult(
                false,
                [__($message)],
                [$statusCode]
            );
        }
    }

    /**
     * @return int|null
     */
    public function getStatusCode()
    {
        $statusCode = $this->transaction->getStatusCode();

        if ((!isset($statusCode) || $statusCode == null) && $this->transaction->isCanceled()) {
            $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        }

        if ((!isset($statusCode) || $statusCode == null)
            && $this->request->getParam('cancel')
        ) {
            $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER');
        }

        return $statusCode;
    }
}
