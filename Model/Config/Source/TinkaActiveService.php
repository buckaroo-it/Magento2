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

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TinkaActiveService implements OptionSourceInterface
{
    public const CREDIT = "Credit";
    public const INVOICE = "Invoice";
    public const ZERO_PERCENT_CREDIT = "ZeroPercentCredit";
    public const ZERO_PERCENT_24MONTHS = "ZeroPercent24Months";
    public const ZERO_PERCENT_36MONTHS = "ZeroPercent36Months";
    public const FASHION_LOAN = "FashionLoan";
    public const PAY_IN3 = "PayIn3";

    public const LIST = [
        self::CREDIT,
        self::ZERO_PERCENT_24MONTHS,
        self::ZERO_PERCENT_36MONTHS,
        self::ZERO_PERCENT_CREDIT,
        self::INVOICE,
        self::FASHION_LOAN,
        self::PAY_IN3,
    ];

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return array_map(
            function ($service) {
                return ['value' => $service, 'label' => $service];
            },
            self::LIST
        );
    }
}
