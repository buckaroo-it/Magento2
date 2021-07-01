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

namespace Buckaroo\Magento2\Model\ConfigProvider;

class SecondChance extends AbstractConfigProvider
{
    const XPATH_SECONDCHANCE_TEMPLATE          = 'buckaroo_magento2/account/second_chance_template';
    const XPATH_SECONDCHANCE_TEMPLATE2         = 'buckaroo_magento2/account/second_chance_template2';
    const XPATH_SECONDCHANCE_DEFAULT_TEMPLATE  = 'buckaroo_second_chance';
    const XPATH_SECONDCHANCE_DEFAULT_TEMPLATE2 = 'buckaroo_second_chance2';
    const XPATH_SECONDCHANCE_FINAL_STATUS      = 10;

    /**
     * {@inheritdoc}
     */
    public function getConfig($store = null)
    {
        $config = [
            'template'          => $this->getTemplate($store),
            'template2'         => $this->getTemplate2($store),
            'default_template'  => self::XPATH_SECONDCHANCE_DEFAULT_TEMPLATE,
            'default_template2' => self::XPATH_SECONDCHANCE_DEFAULT_TEMPLATE2,
            'final_status'      => self::XPATH_SECONDCHANCE_FINAL_STATUS,
        ];
        return $config;
    }
}
