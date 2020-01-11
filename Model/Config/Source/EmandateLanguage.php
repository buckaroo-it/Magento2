<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

namespace TIG\Buckaroo\Model\Config\Source;

class EmandateLanguage implements \Magento\Framework\Option\ArrayInterface
{
    const EMANDATE_LANGUAGE_DUTCH = 'nl_NL';
    const EMANDATE_LANGUAGE_ENGLISH = 'en_US';

    public function toOptionArray()
    {
        $options = [];

        $options[] = ['value' => self::EMANDATE_LANGUAGE_DUTCH, 'label' => __('Dutch')];
        $options[] = ['value' => self::EMANDATE_LANGUAGE_ENGLISH, 'label' => __('English')];

        return $options;
    }
}
