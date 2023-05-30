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

namespace Buckaroo\Magento2\Model\Checks\CanUseForCountry;

use Magento\Payment\Model\Checks\CanUseForCountry\CountryProvider as MagentoCountryProvider;
use Magento\Quote\Model\Quote;

/**
 * Select country which will be used for payment.
 *
 * This class may be extended if logic fo country selection should be modified.
 *
 * @api
 * @since 100.0.2
 */
class CountryProvider extends MagentoCountryProvider
{
    /**
     * Get payment country
     *
     * @param Quote $quote
     * @return string
     */
    public function getCountry(Quote $quote): string
    {
        $address = $quote->getShippingAddress() ?: $quote->getBillingAddress();
        return (!empty($address->getCountry()))
            ? $address->getCountry()
            : $this->directoryHelper->getDefaultCountry();
    }
}
