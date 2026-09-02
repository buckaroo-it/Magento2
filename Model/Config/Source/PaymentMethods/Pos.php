<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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
    protected $config;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @param Config $config
     * @param Data   $helper
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
            ['value' => '', 'label' => __('Hide all methods')],
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
