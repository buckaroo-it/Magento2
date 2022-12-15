<?php

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
 */
class HttppostPushRequest extends AbstractPushRequest implements PushRequestInterface
{
    private array $request = [];
    private array $originalRequest;
    /**
     * @var ValidatorPush $validator
     */
    private ValidatorPush $validator;

    public function __construct(array $requestData, ValidatorPush $validator)
    {
        $this->originalRequest = $requestData;
        /** Magento may adds the SID session parameter, depending on the store configuration.
         * We don't need or want to use this parameter, so remove it from the retrieved post data. */
        unset($requestData['SID']);
        $this->request = array_change_key_case($requestData, CASE_LOWER);
        $this->validator = $validator;
    }

    public function validate($store = null): bool
    {
        return $this->validator->validateSignature(
            $this->originalRequest,
            $this->request,
            $store
        );
    }

    public function get($name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        } else {
            $propertyName = 'brq_' . strtolower(preg_replace('~(?!^)(?=[A-Z])~', '_', $name));
            if (isset($this->request[$propertyName])) {
                return $this->request[$propertyName];
            }
        }
        return null;
    }

    public function getData(): array
    {
        return $this->request;
    }

    public function getOriginalRequest()
    {
        return $this->originalRequest;
    }

    /**
     * @return float|null
     */
    public function getAmount()
    {
        return $this->request['brq_amount'] ?? null;
    }

    /**
     * @return float|null
     */
    public function getAmountDebit()
    {
        return $this->request['brq_amountdebit'] ?? null;
    }

    public function getCurrency()
    {
        return $this->request['brq_currency'] ?? null;
    }

    public function getCustomerName()
    {
        return $this->request['brq_customer_name'] ?? null;
    }

    public function getDescription()
    {
        return $this->request['brq_description'] ?? null;
    }

    public function getInvoiceNumber()
    {
        return $this->request['brq_invoicenumber'] ?? null;
    }

    public function getMutationType()
    {
        return $this->request['brq_mutationtype'] ?? null;
    }

    public function getOrderNumber()
    {
        return $this->request['brq_ordernumber'] ?? null;
    }

    public function getPayerHash()
    {
        return $this->request['brq_payer_hash'] ?? null;
    }

    public function getPayment()
    {
        return $this->request['brq_payment'] ?? null;
    }

    public function getStatusCode()
    {
        return $this->request['brq_statuscode'] ?? null;
    }

    public function getStatusCodeDetail()
    {
        return $this->request['brq_statuscode_detail'] ?? null;
    }

    public function getStatusMessage()
    {
        return $this->request['brq_statusmessage'] ?? null;
    }

    public function getTransactionMethod()
    {
        return $this->request['brq_transaction_method'] ?? null;
    }

    public function getTransactionType()
    {
        return $this->request['brq_transaction_type'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getTransactions()
    {
        return $this->request['brq_transactions'] ?? null;
    }

    public function getAdditionalInformation($propertyName)
    {
        $propertyName = 'add_' . strtolower($propertyName);
        if (isset($this->request[$propertyName])) {
            return $this->request[$propertyName];
        }

        return null;
    }

    public function isTest()
    {
        return $this->request['brq_test'] ?? null;
    }

    public function setTransactions($transactions)
    {
        $this->request['brq_transactions'] = $transactions;
    }

    public function setAmount($amount)
    {
        $this->request['brq_amount'] = $amount;
    }
}
