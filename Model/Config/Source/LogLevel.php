<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world‑wide‑web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world‑wide‑web, please email
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
use Monolog\Level;
use Monolog\Logger;

/**
 * Provide log‑level choices for the system‑config dropdown.
 *
 * Compatible with both Monolog <3 (Logger::getLevels()) and ≥3 (Level enum).
 */
class LogLevel implements OptionSourceInterface
{
    /**
     * {@inheritDoc}
     *
     * @param bool $withEmpty Whether to prepend an empty option.
     * @return array<array{value:int|string,label:\Magento\Framework\Phrase}>
     */
    public function toOptionArray($withEmpty = true): array
    {
        $options = [];

        if ($withEmpty) {
            $options[] = [
                'value' => '',
                'label' => __('-- Use store default --'),
            ];
        }

        /* -----------------------------------------------------------------
         * Monolog <3 provided Logger::getLevels() (array name=>int).
         * Monolog 3 removed it and introduced the Level enum.
         * We detect at runtime and build the list accordingly.
         * ----------------------------------------------------------------*/
        if (method_exists(Logger::class, 'getLevels')) {
            // Monolog 1.x / 2.x
            foreach (Logger::getLevels() as $name => $value) {
                $options[] = [
                    'value' => $value,
                    'label' => __(ucfirst(strtolower($name))),
                ];
            }
        } else {
            // Monolog 3.x – use Level enum
            foreach (Level::cases() as $case) {
                $options[] = [
                    'value' => $case->value,
                    'label' => __(ucfirst(strtolower($case->name))),
                ];
            }
        }

        return $options;
    }
}
