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

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Sales\Model\Order;

class PushTransactionType
{
    public const BUCK_PUSH_TYPE_TRANSACTION = 'transaction_push';
    public const BUCK_PUSH_TYPE_INVOICE = 'invoice_push';
    public const BUCK_PUSH_TYPE_INVOICE_INCOMPLETE = 'incomplete_invoice_push';
    public const BUCK_PUSH_TYPE_DATAREQUEST = 'datarequest_push';

    /**
     * @var string
     */
    private string $paymentMethod;

    /**
     * @var string
     */
    private string $serviceAction;

    /**
     * @var string
     */
    private string $statusCode;

    /**
     * @var string|null
     */
    private string $statusKey;

    /**
     * @var string|null
     */
    private string $statusMessage;

    /**
     * @var bool
     */
    private bool $groupTransaction;

    /**
     * @var bool
     */
    private bool $creditManagment;

    /**
     * @var bool|string
     */
    private string|bool $transactionType;


    /**
     * @var array
     */
    private array $pushTransactionType = [];

    /**
     * @var Order
     */
    private Order $order;

    /**
     * @var OrderRequestService
     */
    private OrderRequestService $orderRequestService;

    /**
     * @param BuckarooStatusCode $buckarooStatusCode
     */
    public function __construct(BuckarooStatusCode $buckarooStatusCode)
    {
        $this->buckarooStatusCode = $buckarooStatusCode;
    }

    /**
     * @param PushRequestInterface|null $pushRequest
     * @param Order|null $order
     * @return PushTransactionType
     */
    public function getPushTransactionType(?PushRequestInterface $pushRequest, ?Order $order): PushTransactionType
    {
        if(empty($this->pushTransactionType)) {
            $this->paymentMethod = $pushRequest->getTransactionMethod();
            $this->transactionType = $this->getTransactionTypeByInvoiceKey($pushRequest, $order);
            $this->statusCode = $this->getStatusCodeByTransactionType($this->transactionType, $pushRequest);
            $this->statusMessage = $this->buckarooStatusCode->getResponseMessage($this->statusCode);
            $this->statusKey = $this->buckarooStatusCode->getStatusKey($this->statusCode);


            $this->pushTransactionType = [
                'paymentMethod'   => $this->paymentMethod,
                'transactionType' => $this->transactionType,
                'statusCode'      => $this->statusCode,
                'statusMessage'   => $this->statusMessage,
                'statusKey'       => $this->statusKey
            ];

        }

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     * @param string $paymentMethod
     */
    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * @return string
     */
    public function getServiceAction(): string
    {
        return $this->serviceAction;
    }

    /**
     * @param string $serviceAction
     */
    public function setServiceAction(string $serviceAction): void
    {
        $this->serviceAction = $serviceAction;
    }

    /**
     * @return string
     */
    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    /**
     * @param string $statusCode
     */
    public function setStatusCode(string $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return string
     */
    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /**
     * @param string $statusMessage
     */
    public function setStatusMessage(string $statusMessage): void
    {
        $this->statusMessage = $statusMessage;
    }

    /**
     * @return BuckarooStatusCode
     */
    public function getBuckarooStatusCode(): BuckarooStatusCode
    {
        return $this->buckarooStatusCode;
    }

    /**
     * @param BuckarooStatusCode $buckarooStatusCode
     */
    public function setBuckarooStatusCode(BuckarooStatusCode $buckarooStatusCode): void
    {
        $this->buckarooStatusCode = $buckarooStatusCode;
    }

    /**
     * @return bool
     */
    public function isGroupTransaction(): bool
    {
        return $this->groupTransaction;
    }

    /**
     * @param bool $groupTransaction
     */
    public function setGroupTransaction(bool $groupTransaction): void
    {
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * @return bool
     */
    public function isCreditManagment(): bool
    {
        return $this->creditManagment;
    }

    /**
     * @param bool $creditManagment
     */
    public function setCreditManagment(bool $creditManagment): void
    {
        $this->creditManagment = $creditManagment;
    }

    /**
     * @return string
     */
    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    /**
     * @param string $transactionType
     */
    public function setTransactionType(string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    /**
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * Determine the transaction type based on push request data and the saved invoice key.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getTransactionTypeByInvoiceKey($pushRequest, $order)
    {
        //If an order has an invoice key, then it should only be processed by invoice pushes
        $savedInvoiceKey = (string)$order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (!empty($pushRequest->getInvoicekey())
            && !empty($pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) > 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE;
        }

        if (!empty($pushRequest->getInvoicekey())
            && !empty($pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) == 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE;
        }

        if (!empty($pushRequest->getDatarequest())) {
            return self::BUCK_PUSH_TYPE_DATAREQUEST;
        }

        if (empty($pushRequest->getInvoicekey())
            && empty($pushRequest->getServiceCreditmanagement3Invoicekey())
            && empty($pushRequest->getDatarequest())
            && strlen($savedInvoiceKey) <= 0
        ) {
            return self::BUCK_PUSH_TYPE_TRANSACTION;
        }

        return '';
    }

    /**
     * Retrieve the status code from the push request based on the transaction type.
     *
     * @param string $transactionType
     * @param PushRequestInterface $pushRequest
     * @return int
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getStatusCodeByTransactionType(
        string $transactionType,
        PushRequestInterface $pushRequest
    ): int {
        $statusCode = 0;
        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                if ($pushRequest->getStatusCode() !== null) {
                    $statusCode = $pushRequest->getStatusCode();
                }
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                if (!empty($pushRequest->getEventparametersStatuscode())) {
                    $statusCode = $pushRequest->getEventparametersStatuscode();
                }

                if (!empty($pushRequest->getEventparametersTransactionstatuscode())) {
                    $statusCode = $pushRequest->getEventparametersTransactionstatuscode();
                }
                break;
            default:
                $statusCode = BuckarooStatusCode::FAILED;
        }

        $statusCodeSuccess = BuckarooStatusCode::SUCCESS;
        if ($pushRequest->getStatusCode() !== null
            && ($pushRequest->getStatusCode() == $statusCodeSuccess)
            && !$statusCode
        ) {
            $statusCode = $statusCodeSuccess;
        }

        return (int)$statusCode;
    }


}