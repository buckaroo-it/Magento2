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

namespace Buckaroo\Magento2\Model\RequestPush;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Model\Validator\Push as ValidatorPush;

/**
 * @method getDatarequest()
 * @method getAmountCredit()
 * @method getRelatedtransactionRefund()
 * @method getInvoicekey()
 * @method getSchemekey()
 * @method getServiceCreditmanagement3Invoicekey()
 * @method getEventparametersStatuscode()
 * @method getEventparametersTransactionstatuscode()
 * @method getIspaid()
 * @method getInvoicestatuscode()
 */
class HttppostPushRequest extends AbstractPushRequest implements PushRequestInterface
{
    /**
     * @var array
     */
    private array $request = [];

    /**
     * @var array
     */
    private array $originalRequest;

    /**
     * @var ValidatorPush $validator
     */
    private ValidatorPush $validator;

    /**
     * @param array $requestData
     * @param ValidatorPush $validator
     */
    public function __construct(array $requestData, ValidatorPush $validator)
    {
        $this->originalRequest = $requestData;
        /** Magento may add the SID session parameter, depending on the store configuration.
         * We don't need or want to use this parameter, so remove it from the retrieved post data. */
        unset($requestData['SID']);
        $this->request = array_change_key_case($requestData);
        $this->validator = $validator;
    }

    /**
     * @inheritdoc
     */
    public function validate($store = null): bool
    {
        return $this->validator->validateSignature(
            $this->originalRequest,
            $this->request,
            $store
        );
    }

    /**
     * Retrieves the value of the specified property or method result.
     *
     * @param string $name
     * @return mixed|null
     */
    public function get(string $name)
    {
        $result = null;

        if (method_exists($this, $name)) {
            $result = $this->$name();
        } elseif (property_exists($this, $name)) {
            $result = $this->$name;
        } else {
            $propertyName = 'brq_' . strtolower(preg_replace('~(?!^)(?=[A-Z])~', '_', $name));
            if (isset($this->request[$propertyName])) {
                $result = $this->request[$propertyName];
            }
        }
        return $result;
    }

    /**
     * Get entire request
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->request;
    }

    /**
     * Get original request without keys modified
     *
     * @return array
     */
    public function getOriginalRequest()
    {
        return $this->originalRequest;
    }

    /**
     * @inheritdoc
     */
    public function getAmount()
    {
        return $this->request['brq_amount'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getAmountDebit()
    {
        return $this->request['brq_amountdebit'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrency(): ?string
    {
        return $this->request['brq_currency'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getCustomerName(): ?string
    {
        return $this->request['brq_customer_name'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->request['brq_description'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceNumber(): ?string
    {
        return $this->request['brq_invoicenumber'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getMutationType(): ?string
    {
        return $this->request['brq_mutationtype'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getOrderNumber(): ?string
    {
        return $this->request['brq_ordernumber'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getPayerHash(): ?string
    {
        return $this->request['brq_payer_hash'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getPayment(): ?string
    {
        return $this->request['brq_payment'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getStatusCode(): ?string
    {
        return (string)$this->request['brq_statuscode'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getStatusCodeDetail(): ?string
    {
        return $this->request['brq_statuscode_detail'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getStatusMessage(): ?string
    {
        return $this->request['brq_statusmessage'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionMethod(): ?string
    {
        return $this->request['brq_transaction_method'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionType(): ?string
    {
        return $this->request['brq_transaction_type'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentMethod(): ?string
    {
        return $this->request['brq_payment_method'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getTransactions(): ?string
    {
        return $this->request['brq_transactions'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalInformation(string $propertyName): ?string
    {
        $propertyName = 'add_' . strtolower($propertyName);
        if (isset($this->request[$propertyName])) {
            return $this->request[$propertyName];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function isTest(): bool
    {
        return $this->request['brq_test'] ?? false;
    }

    /**
     * @inheritdoc
     */
    public function setTransactions($transactions)
    {
        $this->request['brq_transactions'] = $transactions;
    }

    /**
     * @inheritdoc
     */
    public function setAmount($amount)
    {
        $this->request['brq_amount'] = $amount;
    }
}
