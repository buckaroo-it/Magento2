<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayPerEmail;
use Buckaroo\Magento2\Model\ConfigProvider\Method\SepaDirectDebit;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Sofortbanking;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class SepaDirectDebitProcessor extends DefaultProcessor
{
    /**
     * @inheritdoc
     */
    protected function canProcessPendingPush(): bool
    {
        return true;
    }
}
