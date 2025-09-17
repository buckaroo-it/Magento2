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

namespace Buckaroo\Magento2\Model\Config\Source\PaymentMethods;

use Buckaroo\Magento2\Helper\Data;
use Magento\Config\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Pos implements OptionSourceInterface
{
    /**
     * @var Config
     */
    protected Config $config;

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @param Config $config
     * @param Data $helper
     */
    public function __construct(
        Config $config,
        Data $helper
    ) {
        $this->config = $config;
        $this->helper = $helper;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => __('hide all methods')],
        ];

        $paymentMethodsList = $this->helper->getPaymentMethodsList();
        foreach ($paymentMethodsList as $paymentMethod) {
            if ($this->config->getConfigDataValue('payment/buckaroo_magento2_' . $paymentMethod['value'] . '/active')
                && ($paymentMethod['value'] != 'pospayment')
            ) {
                $options[] = $paymentMethod;
            }
        }

        return $options;
    }
}
