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

class Business implements OptionSourceInterface
{
    const BUSINESS_B2C = 1;
    const BUSINESS_B2B = 2;
    const BUSINESS_BOTH = 3;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        // Business options
        $options[] = ['value' => self::BUSINESS_B2C, 'label' => __('B2C')];
        $options[] = ['value' => self::BUSINESS_B2B, 'label' => __('B2B')];
        $options[] = ['value' => self::BUSINESS_BOTH, 'label' => __('Both')];

        return $options;
    }
}
