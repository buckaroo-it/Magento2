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

namespace Buckaroo\Magento2\Model\PaypalExpress\Response;

use Buckaroo\Magento2\Api\Data\BreakdownItemInterface;

class BreakdownItem implements BreakdownItemInterface
{
    /**
     * @var float
     */
    protected $total;

    /**
     * @var string
     */
    protected $currencyCode;

    /**
     * @param float  $total
     * @param string $currencyCode
     */
    public function __construct(float $total, string $currencyCode)
    {
        $this->total = $total;
        $this->currencyCode = $currencyCode;
    }

    /**
     * @inheritdoc
     */
    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    /**
     * @inheritdoc
     */
    public function getValue()
    {
        return number_format($this->total, 2);
    }
}
