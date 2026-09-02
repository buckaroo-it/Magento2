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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\Order\ExtendReservation;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Adds an "Extend Klarna Reservation" button to the admin order view
 * for Klarna MOR orders that hold an active reservation.
 */
class AddExtendReservationButton
{
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var ExtendReservation
     */
    private ExtendReservation $extendReservationService;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param ExtendReservation        $extendReservationService
     * @param UrlInterface             $urlBuilder
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ExtendReservation $extendReservationService,
        UrlInterface $urlBuilder,
        BuckarooLoggerInterface $logger
    ) {
        $this->orderRepository          = $orderRepository;
        $this->extendReservationService = $extendReservationService;
        $this->urlBuilder               = $urlBuilder;
        $this->logger                   = $logger;
    }

    /**
     * Add the Extend Klarna Reservation button on the order view page.
     *
     * @param Toolbar       $subject
     * @param AbstractBlock $context
     * @param ButtonList    $buttonList
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforePushButtons(
        Toolbar $subject,
        AbstractBlock $context,
        ButtonList $buttonList
    ) {
        $orderId = $context->getRequest()->getParam('order_id');

        if (!$orderId || $context->getRequest()->getFullActionName() !== 'sales_order_view') {
            return;
        }

        $order = $this->getOrder((int)$orderId);

        if ($order === null || !$this->extendReservationService->canExtend($order)) {
            return;
        }

        $actionUrl = $this->urlBuilder->getUrl(
            'buckaroo/klarna/extendreservation',
            ['order_id' => $orderId]
        );

        $buttonList->add(
            'extendKlarnaReservationButton',
            [
                'label'   => __('Extend Klarna Reservation'),
                'onclick' => sprintf(
                    "confirmSetLocation('%s', '%s')",
                    __('Are you sure you want to extend the Klarna reservation?'),
                    $actionUrl
                ),
                'class'   => 'reset',
            ],
            -1
        );
    }

    /**
     * Load the order, returning null when it cannot be found.
     *
     * @param int $orderId
     *
     * @return Order|null
     */
    private function getOrder(int $orderId): ?Order
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_MOR] Could not load order %s for extend reservation button: %s',
                $orderId,
                $e->getMessage()
            ));
            return null;
        }

        return $order instanceof Order ? $order : null;
    }
}
