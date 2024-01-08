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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

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
     * @throws LocalizedException
     */
    public function validate(array $validationSubject)
    {
        $response = $validationSubject['response']['object'] ?? null;

        if ($response === null && !($response instanceof TransactionResponse)) {
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
        }

        if ($success) {
            $response = $this->createResult(
                true,
                [__('Transaction Success')],
                [$statusCode]
            );
        } else {
            $payment = SubjectReader::readPayment($validationSubject)->getPayment();
            $methodInstanceClass = $payment->getMethodInstance();

            if ($methodInstanceClass->getCode() == 'buckaroo_magento2_klarnakp') {
                $methodInstanceClass::$requestOnVoid = false;
            }

            $message = !empty($this->transaction->getSomeError()) ?
                $this->transaction->getSomeError()
                : 'Gateway rejected the transaction.';
            $response = $this->createResult(
                false,
                [__($message)],
                [$statusCode]
            );
        }

        return $response;
    }

    /**
     * Get Buckaroo status code
     *
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
