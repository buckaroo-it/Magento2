<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TerminalIdDataBuilder implements BuilderInterface
{
    private CookieManagerInterface $cookieManager;

    public function __construct(CookieManagerInterface $cookieManager)
    {
        $this->cookieManager = $cookieManager;
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
        return $this->cookieManager->getCookie('Pos-Terminal-Id');
    }
}
