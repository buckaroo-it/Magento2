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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class Price extends Value
{
    /**
     * Validate that the number is a valid price.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function save()
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $value = $this->getValue();
        if (!empty($value) && !is_numeric($value)) {
            throw new LocalizedException(__("Please enter a valid number: '%1'.", $value));
        }

        return parent::save();
    }
}
