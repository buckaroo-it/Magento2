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
