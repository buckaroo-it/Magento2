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

use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Magento\Sales\Model\Order;

/**
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class PushTransactionType
{
    public const BUCK_PUSH_CANCEL_AUTHORIZE_TYPE = 'I014';
    public const BUCK_PUSH_ACCEPT_AUTHORIZE_TYPE = 'I013';
    public const BUCK_PUSH_GROUPTRANSACTION_TYPE = 'I150';
    public const BUCK_PUSH_IDEAL_PAY             = 'C021';

    public const BUCK_PUSH_TYPE_TRANSACTION        = 'transaction_push';
    public const BUCK_PUSH_TYPE_INVOICE            = 'invoice_push';
    public const BUCK_PUSH_TYPE_INVOICE_INCOMPLETE = 'incomplete_invoice_push';
    public const BUCK_PUSH_TYPE_DATAREQUEST        = 'datarequest_push';

    /**
     * @var string|null
     */
    private $paymentMethod;

    /**
     * @var string|null
     */
    private $magentoServiceAction;

    /**
     * @var string|null
     */
    private $serviceAction;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string|null
     */
    private $statusKey;

    /**
     * @var string|null
     */
    private $statusMessage;

    /**
     * @var bool
     */
    private $groupTransaction;

    /**
     * @var bool
     */
    private $creditManagement;

    /**
     * @var string
     */
    private $pushType;

    /**
     * @var string
     */
    private $transactionType;

    /**
     * @var array
     */
    private $isSet = false;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var BuckarooStatusCode
     */
    private $buckarooStatusCode;

    /**
     * @var PushRequestInterface|null
     */
    private $pushRequest;

    /**
     * @var bool
     */
    private $isFromPayPerEmail;

    /**
     * @param BuckarooStatusCode $buckarooStatusCode
     */
    public function __construct(BuckarooStatusCode $buckarooStatusCode)
    {
        $this->buckarooStatusCode = $buckarooStatusCode;
    }

    /**
     * Initialize the cached push transaction state.
     *
     * @param \Buckaroo\Magento2\Api\Data\PushRequestInterface|null $pushRequest
     * @param Order|null                                            $order
     *
     * @return PushTransactionType
     */
    public function getPushTransactionType(?PushRequestInterface $pushRequest, ?Order $order): PushTransactionType
    {
        if (!$this->isSet) {
            $this->pushRequest = $pushRequest;
            $this->order = $order;

            $this->paymentMethod = $this->pushRequest->getTransactionMethod() ??
                $this->pushRequest->getPrimaryService() ?? '';
            $this->pushType = $this->getPushTypeByInvoiceKey();
            $this->statusCode = $this->getStatusCodeByTransactionType($this->pushType);
            $this->statusMessage = $this->buckarooStatusCode->getResponseMessage($this->statusCode);
            $this->statusKey = $this->buckarooStatusCode->getStatusKey($this->statusCode);
            $this->transactionType = $this->pushRequest->getTransactionType();
            $this->groupTransaction = $this->transactionType === self::BUCK_PUSH_GROUPTRANSACTION_TYPE;
            $this->creditManagement = $this->pushType === self::BUCK_PUSH_TYPE_INVOICE;
            $this->magentoServiceAction = $this->pushRequest->getAdditionalInformation('service_action_from_magento');
            $this->serviceAction = $this->getServiceAction();
            $this->isFromPayPerEmail = !empty($this->pushRequest->getAdditionalInformation('frompayperemail'))
                || $this->pushRequest->getAdditionalInformation('service_action_from_magento') === 'frompayperemail';

            $this->isSet = true;
        }

        return $this;
    }

    /**
     * Determine the transaction type based on push request data and the saved invoice key.
     *
     * @param PushRequestInterface $pushRequest
     * @param Order                $order
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getPushTypeByInvoiceKey(): string
    {
        //If an order has an invoice key, then it should only be processed by invoice pushes
        $savedInvoiceKey = (string)$this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (!empty($this->pushRequest->getInvoicekey())
            && !empty($this->pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) > 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE;
        }

        if (!empty($this->pushRequest->getInvoicekey())
            && !empty($this->pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) == 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE;
        }

        if (!empty($this->pushRequest->getDatarequest())) {
            return self::BUCK_PUSH_TYPE_DATAREQUEST;
        }

        if (empty($this->pushRequest->getInvoicekey())
            && empty($this->pushRequest->getServiceCreditmanagement3Invoicekey())
            && empty($this->pushRequest->getDatarequest())
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
     *
     * @return int
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getStatusCodeByTransactionType(string $transactionType): int
    {
        $statusCode = 0;
        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                $statusCode = $this->pushRequest->getStatusCode() ?: $statusCode;
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                $statusCode = $this->pushRequest->getEventparametersStatuscode() ?: $statusCode;
                $statusCode = $this->pushRequest->getEventparametersTransactionstatuscode() ?: $statusCode;
                break;
        }

        if ($this->pushRequest->getStatusCode() !== null
            && ($this->pushRequest->getStatusCode() == BuckarooStatusCode::SUCCESS)
            && !$statusCode
        ) {
            $statusCode = BuckarooStatusCode::SUCCESS;
        }

        return (int)$statusCode;
    }

    /**
     * Get the resolved Buckaroo status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set the resolved Buckaroo status code.
     *
     * @param int $statusCode
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * Get the resolved status key.
     *
     * @return string
     */
    public function getStatusKey(): string
    {
        return $this->statusKey;
    }

    /**
     * Set the resolved status key.
     *
     * @param string $statusKey
     */
    public function setStatusKey(string $statusKey): void
    {
        $this->statusKey = $statusKey;
    }

    /**
     * Get the Buckaroo transaction type.
     *
     * @return string|null
     */
    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    /**
     * Set the Buckaroo transaction type.
     *
     * @param string|null $transactionType
     */
    public function setTransactionType(?string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    /**
     * Resolve the Magento service action for the push.
     *
     * @return string|null
     */
    public function getServiceAction(): ?string
    {
        if (empty($this->serviceAction)) {
            $this->serviceAction = $this->magentoServiceAction;

            if (!empty($this->pushRequest->getAmountCredit())) {
                if ($this->getStatusKey() !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                    && $this->order->isCanceled()
                    && $this->getTransactionType() == self::BUCK_PUSH_CANCEL_AUTHORIZE_TYPE
                ) {
                    $this->serviceAction = 'cancel_authorize';
                } else {
                    $this->serviceAction = 'refund';
                }
            }
        }

        return $this->serviceAction;
    }

    /**
     * Set the Magento service action for the push.
     *
     * @param string $serviceAction
     */
    public function setServiceAction(string $serviceAction): void
    {
        $this->serviceAction = $serviceAction;
    }

    /**
     * Get the resolved push type.
     *
     * @return string
     */
    public function getPushType(): string
    {
        return $this->pushType;
    }

    /**
     * Set the resolved push type.
     *
     * @param string $pushType
     */
    public function setPushType(string $pushType): void
    {
        $this->pushType = $pushType;
    }

    /**
     * Get the payment method code.
     *
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    /**
     * Set the payment method code.
     *
     * @param string $paymentMethod
     */
    public function setPaymentMethod(string $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get the service action received from Magento.
     *
     * @return string
     */
    public function getMagentoServiceAction(): string
    {
        return $this->magentoServiceAction;
    }

    /**
     * Set the service action received from Magento.
     *
     * @param string $magentoServiceAction
     */
    public function setMagentoServiceAction(string $magentoServiceAction): void
    {
        $this->magentoServiceAction = $magentoServiceAction;
    }

    /**
     * Get the human-readable status message.
     *
     * @return string
     */
    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /**
     * Set the human-readable status message.
     *
     * @param string $statusMessage
     */
    public function setStatusMessage(string $statusMessage): void
    {
        $this->statusMessage = $statusMessage;
    }

    /**
     * Get the Buckaroo status code resolver.
     *
     * @return BuckarooStatusCode
     */
    public function getBuckarooStatusCode(): BuckarooStatusCode
    {
        return $this->buckarooStatusCode;
    }

    /**
     * Set the Buckaroo status code resolver.
     *
     * @param BuckarooStatusCode $buckarooStatusCode
     */
    public function setBuckarooStatusCode(BuckarooStatusCode $buckarooStatusCode): void
    {
        $this->buckarooStatusCode = $buckarooStatusCode;
    }

    /**
     * Check whether this push belongs to a group transaction.
     *
     * @return bool
     */
    public function isGroupTransaction(): bool
    {
        return $this->groupTransaction;
    }

    /**
     * Mark whether this push belongs to a group transaction.
     *
     * @param bool $groupTransaction
     */
    public function setGroupTransaction(bool $groupTransaction): void
    {
        $this->groupTransaction = $groupTransaction;
    }

    /**
     * Check whether this push belongs to credit management.
     *
     * @return bool
     */
    public function isCreditManagment(): bool
    {
        return $this->creditManagement;
    }

    /**
     * Mark whether this push belongs to credit management.
     *
     * @param bool $creditManagement
     */
    public function setCreditManagment(bool $creditManagement): void
    {
        $this->creditManagement = $creditManagement;
    }

    /**
     * Get the Magento order attached to the push.
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Set the Magento order attached to the push.
     *
     * @param Order $order
     */
    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * Check whether the push originated from pay-per-email.
     *
     * @return bool
     */
    public function isFromPayPerEmail(): bool
    {
        return $this->isFromPayPerEmail;
    }

    /**
     * Mark whether the push originated from pay-per-email.
     *
     * @param bool $isFromPayPerEmail
     */
    public function setIsFromPayPerEmail(bool $isFromPayPerEmail): void
    {
        $this->isFromPayPerEmail = $isFromPayPerEmail;
    }
}
