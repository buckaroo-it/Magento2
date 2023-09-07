<?php

namespace Buckaroo\Magento2\Plugin;

class BuckarooAdapterPlugin
{
    protected $subject;

    public function aroundOrder(
        \Magento\Payment\Model\Method\Adapter $subject,
        callable $proceed,
        \Magento\Payment\Model\InfoInterface $payment,
        $amount
    ) {
        $this->subject = $subject; // Storing the subject instance

        $commandCode = 'order';
        if ($this->getConfigData('api_version')) {
            $commandCode .= $this->getConfigData('api_version');
        }

        // Invoke our modified logic
        $this->subject->executeCommand(
            $commandCode,
            ['payment' => $payment, 'amount' => $amount]
        );

        return $subject;
    }

    // Helper method to get config data
    protected function getConfigData($key)
    {
        if (method_exists($this->subject, 'getConfigData')) {
            return $this->subject->getConfigData($key);
        }
        return null;
    }
}
