<?php

namespace Buckaroo\Magento2\Plugin;

class FixPPEStatusCode
{
    public function aroundGetStatusCode(
        \Buckaroo\Magento2\Model\Push $subject,
        callable $proceed
    ) {
        // Let the original method try first
        $result = $proceed();


        // If it failed to pick up the code (0) but the push does have brq_statuscode,
        // fall back to that value.
        if ($result === 0 && isset($subject->postData['brq_statuscode'])) {
            $result = $subject->postData['brq_statuscode'];
        }

        return $result;
    }
}
