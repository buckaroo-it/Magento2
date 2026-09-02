<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class TerminalIdDataBuilder implements BuilderInterface
{
    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(CookieManagerInterface $cookieManager)
    {
        $this->cookieManager = $cookieManager;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function build(array $buildSubject): array
    {
        return ['terminalID' => $this->getPosPaymentTerminalId()];
    }

    /**
     * Get Pos Terminal ID Cookie
     *
     * @return null|string
     */
    private function getPosPaymentTerminalId(): ?string
    {
        return $this->cookieManager->getCookie('Pos-Terminal-Id');
    }
}
