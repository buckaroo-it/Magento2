<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Buckaroo\Magento2\Gateway\Http\Client\ClientMock;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class ResponseCodeValidator extends AbstractValidator
{
    const RESULT_CODE = 'RESULT_CODE';

    /**
     * @var \Buckaroo\Magento2\Helper\Data $helper
     */
    protected $helper;

    /**
     * @var \StdClass
     */
    protected $transaction;

    protected $request;

    /**
     * @param \Buckaroo\Magento2\Helper\Data $helper
     * @param \Magento\Framework\App\Request\Http $request
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        \Buckaroo\Magento2\Helper\Data      $helper,
        \Magento\Framework\App\Request\Http $request,
        ResultInterfaceFactory              $resultFactory
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
     */
    public function validate(array $validationSubject)
    {
        if (!isset($validationSubject['response']) || !is_array($validationSubject['response'])) {
            throw new \InvalidArgumentException('Response does not exist');
        }

        $response = $validationSubject['response'];

        if (empty($response[0]) || !$response[0] instanceof \StdClass) {
            return $this->createResult(
                false,
                [__('Data must be an instance of "\StdClass"')]
            );
        }

        $this->transaction = $response[0];
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
            return $this->createResult(
                false,
                [__('Gateway rejected the transaction.')],
                [$statusCode]
            );
        }
    }

    /**
     * @return int|null
     */
    public function getStatusCode()
    {
        $statusCode = null;

        if (isset($this->transaction->Status)) {
            $statusCode = $this->transaction->Status->Code->Code;
        }

        if ((!isset($statusCode) || $statusCode == null)
            && isset($this->transaction->Transaction->IsCanceled)
            && $this->transaction->Transaction->IsCanceled == true
        ) {
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
