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
use Buckaroo\Magento2\Service\Config\AdminConfigScope;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Pos implements OptionSourceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var AdminConfigScope
     */
    private $adminConfigScope;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Data                 $helper
     * @param AdminConfigScope     $adminConfigScope
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Data $helper,
        AdminConfigScope $adminConfigScope
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->adminConfigScope = $adminConfigScope;
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

        // Active at the scope being edited, not at default scope
        [$scopeType, $scopeId] = $this->adminConfigScope->getScope();

        $paymentMethodsList = $this->helper->getPaymentMethodsList();
        foreach ($paymentMethodsList as $paymentMethod) {
            $activePath = 'payment/buckaroo_magento2_' . $paymentMethod['value'] . '/active';
            if ($this->scopeConfig->getValue($activePath, $scopeType, $scopeId)
                && ($paymentMethod['value'] != 'pospayment')
            ) {
                $options[] = $paymentMethod;
            }
        }

        return $options;
    }
}
