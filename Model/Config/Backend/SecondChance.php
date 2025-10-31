<?php
/**
 * Copyright © 2015 Buckaroo B.V.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

class SecondChance extends Value
{
    /**
     * Validate the configuration value before saving
     *
     * @throws ValidatorException
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $path = $this->getPath();

        // Validate timing configurations (should be between 0-10080 minutes - 1 week)
        if (strpos($path, 'timing') !== false) {
            if (!is_numeric($value) || $value < 0 || $value > 10080) {
                throw new ValidatorException(__('Timing value must be between 0 and 10080 minutes (1 week).'));
            }
        }

        // Validate prune days (should be between 1-365 days)
        if (strpos($path, 'prune_days') !== false) {
            if (!is_numeric($value) || $value < 1 || $value > 365) {
                throw new ValidatorException(__('Prune days must be between 1 and 365 days.'));
            }
        }

        // Validate boolean values
        if (strpos($path, 'enabled') !== false || strpos($path, 'auto_send') !== false) {
            if (!in_array($value, [0, 1, '0', '1'], true)) {
                throw new ValidatorException(__('Value must be 0 or 1.'));
            }
        }

        return parent::beforeSave();
    }
}
