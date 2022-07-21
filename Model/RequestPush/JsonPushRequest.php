<?php

namespace Buckaroo\Magento2\Model\RequestPush;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception;

/**
 * @method getDatarequest()
 * @method getAmountCredit()
 * @method getRelatedtransactionRefund()
 * @method getInvoicekey()
 * @method getSchemekey()
 * @method getServiceCreditmanagement3Invoicekey()
 */
class JsonPushRequest implements PushRequestInterface
{
    private array $request = [];
    private array $originalRequest;

    /**
     * @throws Exception
     */
    public function __construct(array $requestData)
    {
        $this->originalRequest = $requestData;
        if(isset($requestData['Transaction'])) {
            $this->request = $requestData['Transaction'];
        } else {
            throw new Exception(__('Json request could not be processed, please use httppost'));
        }

    }

    public function validate($store = null): bool
    {
        return true;
    }

    public function getAmount()
    {
        return $this->request["AmountDebit"] ?? null;
    }

    public function getAmountDebit()
    {
        return $this->request["AmountDebit"] ?? null;
    }

    public function getCurrency()
    {
        return $this->request["Currency"] ?? null;
    }

    public function getCustomerName()
    {
        return $this->request["CustomerName"] ?? null;
    }

    public function getDescription()
    {
        return $this->request["Description"] ?? null;
    }

    public function getInvoiceNumber()
    {
        return $this->request["Invoice"] ?? null;
    }

    public function getMutationType()
    {
        return $this->request["MutationType"] ?? null;
    }

    public function getOrderNumber()
    {
        return $this->request["Order"] ?? null;
    }

    public function getPayerHash()
    {
        return $this->request["PayerHash"] ?? null;
    }

    public function getPayment()
    {
        return $this->request["PaymentKey"] ?? null;
    }

    public function getStatusCode()
    {
        return $this->request["Status"]["Code"]["Code"] ?? null;
    }

    public function getStatusCodeDetail()
    {
        return $this->request["Status"]["SubCode"]["Code"]?? null;
    }

    public function getStatusMessage()
    {
        return $this->request["Status"]["SubCode"]["Description"] ?? null;
    }

    public function isTest()
    {
        return $this->request["IsTest"] ?? null;
    }

    public function getTransactionMethod()
    {
        return $this->request["ServiceCode"] ?? null;
    }

    public function getTransactionType()
    {
        return $this->request["TransactionType"] ?? null;
    }

    public function getTransactions()
    {
        return $this->request["Key"] ?? null;
    }

    public function getOriginalRequest()
    {
        return $this->originalRequest;
    }

    public function setTransactions($transactions)
    {
        $this->request['Key'] = $transactions;
    }

    public function setAmount($amount) {
        $this->request['AmountDebit'] = $amount;
    }

    public function getData(): array
    {
        return $this->request;
    }

    public function getAdditionalInformation($propertyName)
    {
        if(isset($this->request['AdditionalParameters']['List']) && is_array($this->request['AdditionalParameters']['List']))
        {
            foreach ($this->request['AdditionalParameters']['List'] as $parameter) {
                if(isset($parameter['Name']) && $parameter['Name'] == $propertyName) {
                    return $parameter['Value'] ?? null;
                }
            }
        }

        return null;
    }

    public function hasPostData($name, $value): bool
    {
        $getter = 'get' . str_replace('_', '', ucwords($name, '_'));
        $fieldValue = $this->$getter();
        if (is_array($value) &&
            isset($fieldValue) &&
            in_array($fieldValue, $value)
        ) {
            return true;
        }

        if (isset($fieldValue) &&
            $fieldValue == $value
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param $name
     * @param $value
     * @return bool
     */
    public function hasAdditionalInformation($name, $value): bool
    {
        $fieldValue = $this->getAdditionalInformation($name);
        if (is_array($value) &&
            isset($fieldValue) &&
            in_array($fieldValue, $value)
        ) {
            return true;
        }

        if (isset($fieldValue) &&
            $fieldValue == $value
        ) {
            return true;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function __call($methodName, $args) {
        if(method_exists($this, $methodName)) {
            call_user_func_array([$this, $methodName], $args);
        }
        if (preg_match('~^(set|get)(.*)$~', $methodName, $matches)) {
            $property = lcfirst($matches[2]);
            switch($matches[1]) {
                case 'set':
                    $this->checkArguments($args, 1, 1, $methodName);
                    return $this->set($property, $args[0]);
                case 'get':
                    $this->checkArguments($args, 0, 0, $methodName);
                    return $this->get($property);
                default:
                    throw new \Exception('Method ' . $methodName . ' not exists');
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function checkArguments(array $args, $min, $max, $methodName) {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new \Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }

    public function get($name){
        if(method_exists($this, $name)){
            return $this->$name();
        }
        elseif(property_exists($this,$name)){
            return $this->$name;
        } else {
            $propertyName = ucfirst($name);
            if(isset($this->request[$propertyName])) {
                return $this->request[$propertyName];
            }
        }
        return null;
    }
}
