<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TerminalIdDataBuilder implements BuilderInterface
{
    private BuckarooLog $buckarooLog;
    private CookieManagerInterface $cookieManager;

    public function __construct(CookieManagerInterface $cookieManager, BuckarooLog $buckarooLog)
    {
        $this->cookieManager = $cookieManager;
        $this->buckarooLog = $buckarooLog;
    }

    public function build(array $buildSubject): array
    {
        return ['terminalID' => $this->getPosPaymentTerminalId()];
    }

    /**
     * @return null|string
     */
    private function getPosPaymentTerminalId(): ?string
    {
        $terminalId = $this->cookieManager->getCookie('Pos-Terminal-Id');
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');
        $this->buckarooLog->addDebug(var_export($terminalId, true));
        return $terminalId;
    }

}
