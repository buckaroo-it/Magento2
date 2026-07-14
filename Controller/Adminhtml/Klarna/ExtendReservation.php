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

namespace Buckaroo\Magento2\Controller\Adminhtml\Klarna;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\Order\ExtendReservation as ExtendReservationService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Extend the Klarna MOR reservation for an order from the admin order view.
 */
class ExtendReservation extends Action implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Buckaroo_Magento2::extend_reservation';

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * @var ExtendReservationService
     */
    private ExtendReservationService $extendReservationService;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param Context                  $context
     * @param OrderRepositoryInterface $orderRepository
     * @param ExtendReservationService $extendReservationService
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        ExtendReservationService $extendReservationService,
        BuckarooLoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->orderRepository          = $orderRepository;
        $this->extendReservationService = $extendReservationService;
        $this->logger                   = $logger;
    }

    /**
     * Extend the Klarna MOR reservation and redirect back to the order view.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $orderId = $this->getRequest()->getParam('order_id');

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $this->redirectToReferer();
        }

        $order = $this->getOrder((int)$orderId);

        if ($order === null || !$this->extendReservationService->canExtend($order)) {
            $this->messageManager->addErrorMessage(
                __('This order does not have an active Klarna reservation that can be extended.')
            );
            return $this->redirectToReferer();
        }

        try {
            $this->extendReservationService->execute($order);
            $this->messageManager->addSuccessMessage(__('The Klarna reservation has been extended.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage(
                __('Unable to extend the Klarna reservation: %1', $e->getMessage())
            );
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_MOR] Unexpected error extending reservation for order %s: %s',
                $orderId,
                $e->getMessage()
            ));
            $this->messageManager->addErrorMessage(
                __('An unexpected error occurred while extending the Klarna reservation.')
            );
        }

        return $this->redirectToReferer();
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
                '[KLARNA_MOR] Could not load order %s for extend reservation: %s',
                $orderId,
                $e->getMessage()
            ));
            return null;
        }

        return $order instanceof Order ? $order : null;
    }

    /**
     * Redirect back to the referring page (the order view).
     *
     * @return ResultInterface
     */
    private function redirectToReferer(): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setUrl($this->_redirect->getRefererUrl());
    }
}
