<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Response;

use Buckaroo\Magento2\Api\Data\BreakdownItemInterface;

class BreakdownItem implements BreakdownItemInterface
{
    protected $total;

    protected $currencyCode;

    public function __construct(float $total, string $currencyCode)
    {
        $this->total = $total;
        $this->currencyCode = $currencyCode;
    }

    /** @inheritDoc */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /** @inheritDoc */
    public function getValue()
    {
        return number_format($this->total, 2);
    }
}
