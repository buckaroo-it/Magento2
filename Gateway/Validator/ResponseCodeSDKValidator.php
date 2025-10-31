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
use Buckaroo\Magento2\Model\Transaction\Status\Response;
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
    protected $helper;

    /**
     * @var TransactionResponse
     */
    protected $transaction;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @param Data                   $helper
     * @param Http                   $request
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
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @throws LocalizedException
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = $validationSubject['response']['object'] ?? null;

        if (!$response instanceof TransactionResponse) {
            return $this->createResult(false, [__('Data must be an instance of "TransactionResponse"')]);
        }

        $this->transaction = $response;
        $statusCode = $this->getStatusCode();

        if ($this->isSuccessStatusCode($statusCode)) {
            return $this->createResult(true, [__('Transaction Success')], [$statusCode]);
        } elseif ($this->isFailedStatusCode($statusCode)) {
            return $this->handleFailureStatusCode($validationSubject, $statusCode);
        }

        return $this->createResult(false, [__("Invalid Buckaroo status code received: %1.")], [$statusCode]);
    }

    /**
     * Get Buckaroo status code
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        $statusCode = $this->transaction->getStatusCode();

        if ((!isset($statusCode) || $statusCode == null) && $this->transaction->isCanceled()) {
            $statusCode = Response::STATUSCODE_SUCCESS;
        }

        if ((!isset($statusCode) || $statusCode == null)
            && $this->request->getParam('cancel')
        ) {
            $statusCode = Response::STATUSCODE_CANCELLED_BY_USER;
        }

        return $statusCode;
    }

    private function isSuccessStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, [
            Response::STATUSCODE_SUCCESS,
            Response::STATUSCODE_PENDING_PROCESSING,
            Response::STATUSCODE_WAITING_ON_USER_INPUT,
            Response::STATUSCODE_WAITING_ON_CONSUMER,
            Response::STATUSCODE_PAYMENT_ON_HOLD,
        ]);
    }

    private function isFailedStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, [
            Response::ORDER_FAILED,
            Response::STATUSCODE_VALIDATION_FAILURE,
            Response::STATUSCODE_TECHNICAL_ERROR,
            Response::STATUSCODE_FAILED,
            Response::STATUSCODE_REJECTED,
            Response::STATUSCODE_CANCELLED_BY_USER,
            Response::STATUSCODE_CANCELLED_BY_MERCHANT,
        ]);
    }

    /**
     * @param array $validationSubject
     * @param ?int  $statusCode
     *
     * @throws LocalizedException
     */
    private function handleFailureStatusCode(array $validationSubject, ?int $statusCode): ResultInterface
    {
        $payment = SubjectReader::readPayment($validationSubject)->getPayment();
        $methodInstanceClass = $payment->getMethodInstance();

        if ($methodInstanceClass->getCode() == 'buckaroo_magento2_klarnakp') {
            $methodInstanceClass::$requestOnVoid = false;
        }

        $message = !empty($this->transaction->getSomeError()) ?
            $this->transaction->getSomeError()
            : 'Gateway rejected the transaction.';

        if ($statusCode === 690
            && strpos($message, "deliveryCustomer.address.countryCode") !== false
        ) {
            $message = "Pay rejected: It is not allowed to specify another country " .
                "for the invoice and delivery address for Afterpay transactions.";
        }

        $fraudMessage = $this->getFailureMessageOnFraud();
        if ($fraudMessage !== null) {
            $message = $fraudMessage;
        }

        return $this->createResult(false, [__($message)], [$statusCode]);
    }

    /**
     * @return string|null
     */
    public function getFailureMessageOnFraud(): ?string
    {
        if ($this->transaction->getSubStatusCode() == 'S103') {
            return 'An anti-fraud rule has blocked this transaction automatically. Please contact the webshop.';
        }

        return null;
    }
}
