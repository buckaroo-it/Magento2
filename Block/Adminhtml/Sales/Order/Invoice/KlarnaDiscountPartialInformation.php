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

namespace Buckaroo\Magento2\Block\Adminhtml\Sales\Order\Invoice;

use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use LogicException;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\OrderRepositoryInterface;

class KlarnaDiscountPartialInformation extends Template
{
    /**
     * @var Factory
     */
    protected $configProviderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * RoundingWarning constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param Factory                  $configProviderFactory
     * @param Context                  $context
     * @param array                    $data
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        Factory $configProviderFactory,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->orderRepository = $orderRepository;
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
     * Should show the warning regarding partial discount
     *
     * @throws LogicException|LocalizedException
     *
     * @return bool
     */
    protected function shouldShowWarning()
    {
        if ($orderId = $this->getRequest()->getParam('order_id')) {
            $order = $this->orderRepository->get((int)$orderId);
            $payment = $order->getPayment();

            /**
             * The warning should only be shown for partial invoices
             */
            if ($payment->canCapturePartial()) {
                return false;
            }

            /**
             * The warning should only be shown for Klarna Buckaroo payment methods.
             */
            $paymentMethod = $payment->getMethod();
            if (strpos($paymentMethod, 'buckaroo_magento2_klarna') === false) {
                return false;
            }
        }

        return true;
    }
}
