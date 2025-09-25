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

/**
 * @method mixed getValue()
 */
class AllowedIssuers extends Value
{
    /**
     * Validate that at least one issuer is selected
     *
     * @return $this
     * @throws LocalizedException
     */
    public function save()
    {
        $value = (array)$this->getValue();
        
        // Filter out empty values
        $value = array_filter($value);
        
        if (empty($value)) {
            throw new LocalizedException(
                __('You must select at least one credit or debit card for the hosted fields to function properly.')
            );
        }

        return parent::save();
    }
}
