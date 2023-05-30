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
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\Validator\PushSDK as Validator;

/**
 * @method getDatarequest()
 * @method getAmountCredit()
 * @method getRelatedtransactionRefund()
 * @method getInvoicekey()
 * @method getSchemekey()
 * @method getServiceCreditmanagement3Invoicekey()
 * @method getEventparametersStatuscode()
 */
class JsonPushRequest extends AbstractPushRequest implements PushRequestInterface
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
     * @var Validator $validator
     */
    private Validator $validator;

    /**
     * @param array $requestData
     * @param Validator $validator
     * @throws Exception
     */
    public function __construct(array $requestData, Validator $validator)
    {
        $this->originalRequest = $requestData;
        if (isset($requestData['Transaction'])) {
            $this->request = $requestData['Transaction'];
        } else {
            throw new Exception(__('Json request could not be processed, please use httppost'));
        }
        $this->validator = $validator;
    }

    /**
     * @inheritdoc
     */
    public function validate($store = null): bool
    {
        return $this->validator->validate($this->getData());
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
            $propertyName = ucfirst($name);
            if (isset($this->request[$propertyName])) {
                $result = $this->request[$propertyName];
            }
        }
        return $result;
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
    public function getAmountDebit()
    {
        return $this->getAmount();
    }

    /**
     * @inheritdoc
     */
    public function getAmount()
    {
        return $this->request["AmountDebit"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrency(): ?string
    {
        return $this->request["Currency"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getCustomerName(): ?string
    {
        return $this->request["CustomerName"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->request["Description"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceNumber(): ?string
    {
        return $this->request["Invoice"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getMutationType(): ?string
    {
        return $this->request["MutationType"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getOrderNumber(): ?string
    {
        return $this->request["Order"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getPayerHash(): ?string
    {
        return $this->request["PayerHash"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getPayment(): ?string
    {
        return $this->request["PaymentKey"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getStatusCode(): ?string
    {
        return (string)$this->request["Status"]["Code"]["Code"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getStatusCodeDetail(): ?string
    {
        return $this->request["Status"]["SubCode"]["Code"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getStatusMessage(): ?string
    {
        return $this->request["Status"]["SubCode"]["Description"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionMethod(): ?string
    {
        return $this->request["ServiceCode"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionType(): ?string
    {
        return $this->request["TransactionType"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getTransactions(): ?string
    {
        return $this->request["Key"] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalInformation(string $propertyName): ?string
    {
        if (isset($this->request['AdditionalParameters']['List'])
            && is_array($this->request['AdditionalParameters']['List'])) {
            foreach ($this->request['AdditionalParameters']['List'] as $parameter) {
                if (isset($parameter['Name']) && $parameter['Name'] == $propertyName) {
                    return $parameter['Value'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function isTest(): bool
    {
        return $this->request["IsTest"] ?? false;
    }

    /**
     * @inheritdoc
     */
    public function setTransactions($transactions)
    {
        $this->request['Key'] = $transactions;
    }

    /**
     * @inheritdoc
     */
    public function setAmount($amount)
    {
        $this->request['AmountDebit'] = $amount;
    }
}
