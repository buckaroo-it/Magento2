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

namespace Buckaroo\Magento2\Model\Validator;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ValidatorInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Phrase;

class TransactionResponseStatus implements ValidatorInterface
{
    /**
     * @var Data $helper
     */
    protected Data $helper;

    /**
     * @var \StdClass
     */
    protected \StdClass $transaction;

    /**
     * @var Http
     */
    protected Http $request;

    /**
     * @param Data $helper
     * @param Http $request
     */
    public function __construct(Data $helper, Http $request)
    {
        $this->helper = $helper;
        $this->request = $request;
    }

    /**
     * Validate transaction
     *
     * @param array|object $data
     * @return bool
     * @throws BuckarooException|\InvalidArgumentException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function validate($data): bool
    {
        if (empty($data[0]) || !$data[0] instanceof \StdClass) {
            throw new \InvalidArgumentException(
                'Data must be an instance of "\StdClass"'
            );
        }

        $this->transaction = $data[0];
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
                throw new BuckarooException(
                    new Phrase(
                        "Invalid Buckaroo status code received: %1.",
                        [$statusCode]
                    )
                );
        }

        return $success;
    }

    /**
     * Get transaction status code
     *
     * @return int|null
     */
    public function getStatusCode(): ?int
    {
        $statusCode = null;

        if (isset($this->transaction->Status)) {
            $statusCode = $this->transaction->Status->Code->Code;
        }

        if ((!isset($statusCode) || $statusCode == null)
            && isset($this->transaction->Transaction->IsCanceled)
            && $this->transaction->Transaction->IsCanceled
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
