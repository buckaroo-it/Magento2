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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order\Creditmemo\Create;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory as ConfigProviderMethodFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Creditmemo;

class RoundingWarning extends Template
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ConfigProviderMethodFactory
     */
    protected $configProviderFactory;

    /**
     * RoundingWarning constructor.
     *
     * @param Registry $registry
     * @param ConfigProviderMethodFactory $configProviderFactory
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Registry $registry,
        ConfigProviderMethodFactory $configProviderFactory,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->registry = $registry;
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->shouldShowWarning()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * @return bool
     * @throws \LogicException|Exception|LocalizedException
     */
    protected function shouldShowWarning()
    {
        $creditmemo = $this->getCreditmemo();

        /**
         * The warning should only be shown before the refund has been processed.
         */
        if (!$creditmemo->canRefund()) {
            return false;
        }

        /**
         * The warning should only be shown for invoice-based refunds.
         */
        if (!$creditmemo->getInvoice()) {
            return false;
        }

        /**
         * The warning should only be shown when the order's currency is different from the store's base currency.
         */
        if ($creditmemo->getBaseCurrencyCode() == $creditmemo->getOrderCurrencyCode()) {
            return false;
        }

        /**
         * The warning should only be shown for Buckaroo payment methods.
         */
        $payment = $creditmemo->getOrder()->getPayment();
        $paymentMethod = $payment->getMethod();
        if (strpos($paymentMethod, 'buckaroo_magento2') === false) {
            return false;
        }

        /**
         * @var \Buckaroo\Magento2\Model\Method\BuckarooAdapter $paymentMethodInstance
         */
        $paymentMethodInstance = $payment->getMethodInstance();

        /**
         * Check if a config provider exists for the pament method used.
         */
        if (!$this->configProviderFactory->has($paymentMethodInstance->buckarooPaymentMethodCode)) {
            return false;
        }

        /**
         * The warning should only be shown if the order's currency is supported by the payment method used.
         */
        $configProvider = $this->configProviderFactory->get($paymentMethodInstance->buckarooPaymentMethodCode);
        if (!in_array($creditmemo->getOrderCurrencyCode(), $configProvider->getAllowedCurrencies())) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve creditmemo model instance
     *
     * @return Creditmemo
     */
    public function getCreditmemo()
    {
        return $this->registry->registry('current_creditmemo');
    }
}
