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

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayLink;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Api\OrderRepositoryInterface;

class PluginBefore
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param Factory                  $configProviderMethodFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param UrlInterface             $urlBuilder
     */
    public function __construct(
        Factory $configProviderMethodFactory,
        OrderRepositoryInterface $orderRepository,
        UrlInterface $urlBuilder
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->orderRepository = $orderRepository;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Add Paylink button
     *
     * @param Toolbar       $subject
     * @param AbstractBlock $context
     * @param ButtonList    $buttonList
     *
     * @throws BuckarooException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforePushButtons(
        Toolbar $subject,
        AbstractBlock $context,
        ButtonList $buttonList
    ) {
        if ($orderId = $context->getRequest()->getParam('order_id')) {
            $viewUrl = $this->urlBuilder->getUrl('buckaroo/paylink/index/order', ['order_id' => $orderId]);
            $order = $this->orderRepository->get($orderId);
            $state = $order->getState();
            $config = $this->configProviderMethodFactory->get('paylink');
            $this->request = $context->getRequest();
            if ($config->getActive() != '0' &&
                $this->request->getFullActionName() == 'sales_order_view' &&
                $state == 'new' &&
                ($order->getPayment()->getMethod() != PayLink::CODE)
            ) {
                $buttonList->add(
                    'payLinkButton',
                    [
                        'label'   => __('Create Paylink'),
                        'onclick' => sprintf(
                            "confirmSetLocation('%s', '%s')",
                            __('Are you sure you want create Paylink?'),
                            $viewUrl
                        ),
                        'class'   => 'reset',
                    ],
                    -1
                );
            }
        }
    }
}
