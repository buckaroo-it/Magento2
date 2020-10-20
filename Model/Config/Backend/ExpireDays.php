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
namespace Buckaroo\Magento2\Model\Config\Backend;

/**
 * @method mixed getValue
 */
class ExpireDays extends \Magento\Framework\App\Config\Value
{
    /**
     * Test that the value is a integer within 0 and 180 interval
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save()
    {
        $value = intval($this->getValue());

        if (empty($value)) {
            return parent::save();
        }

        if (!is_int($value) || $value < 0 || $value > 180) {
            throw new \Magento\Framework\Exception\LocalizedException(__("Please enter a valid integer within 0 and 180 interval"));
        }

        $this->setValue($value);

        return parent::save();
    }
}
