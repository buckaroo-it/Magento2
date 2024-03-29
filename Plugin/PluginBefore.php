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
namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Model\Method\PayLink;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

class PluginBefore
{
    protected $urlBuider;

    protected $orderRepository;

    protected $configProviderMethodFactory;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Factory $configProviderMethodFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        UrlInterface $urlBuilder
    ) {
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->orderRepository             = $orderRepository;
        $this->urlBuilder                  = $urlBuilder;
    }

    public function beforePushButtons(
        \Magento\Backend\Block\Widget\Button\Toolbar $subject,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {
        if ($orderId = $context->getRequest()->getParam('order_id')) {
            $viewUrl        = $this->urlBuilder->getUrl('buckaroo/paylink/index/order', ['order_id' => $orderId]);
            $order          = $this->orderRepository->get($orderId);
            $state          = $order->getState();
            $config         = $this->configProviderMethodFactory->get('paylink');
            $this->request = $context->getRequest();
            if ($config->getActive() != '0' &&
                $this->request->getFullActionName() == 'sales_order_view' &&
                $state == 'new' &&
                ($order->getPayment()->getMethod() != PayLink::PAYMENT_METHOD_CODE)
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
