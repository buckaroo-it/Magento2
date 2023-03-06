<?php

namespace Buckaroo\Magento2\Gateway\Command;

interface SkipCommandInterface
{
    /**
     * Check if command should be skipped
     *
     * @param array $commandSubject
     * @return bool
     */
    public function isSkip(array $commandSubject): bool;
}
