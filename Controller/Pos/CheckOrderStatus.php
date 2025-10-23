<?php

// @codingStandardsIgnoreFile
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

namespace Buckaroo\Magento2\Controller\Pos;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class CheckOrderStatus extends \Magento\Framework\App\Action\Action
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var Order $order
     */
    protected $order;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Checkout\Model\ConfigProviderInterface
     */
    protected $accountConfig;

    private $storeManager;
    private $urlBuilder;
    private $formKey;
    private $helper;

    /**
     * @param Context            $context
     * @param Log                                              $logger
     * @param Order                       $order
     * @param JsonFactory $resultJsonFactory
     * @param Factory  $configProviderFactory
     * @param StoreManagerInterface       $storeManager
     * @param UrlInterface                  $urlBuilder
     * @param FormKey             $formKey
     * @param Data                   $helper
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        Context $context,
        Log $logger,
        Order $order,
        JsonFactory $resultJsonFactory,
        Factory $configProviderFactory,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Data $helper
    ) {
        parent::__construct($context);
        $this->logger             = $logger;
        $this->order              = $order;
        $this->resultJsonFactory  = $resultJsonFactory;
        $this->accountConfig      = $configProviderFactory->get('account');
        $this->storeManager       = $storeManager;
        $this->urlBuilder         = $urlBuilder;
        $this->formKey            = $formKey;
        $this->helper             = $helper;
    }

    /**
     * Process action
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__.'|1|');
        $response = ['success' => 'false', 'redirect' => ''];

        if (($params = $this->getRequest()->getParams()) && !empty($params['orderId'])) {
            $this->order->loadByIncrementId($params['orderId']);
            if ($this->order->getId()) {
                $store = $this->order->getStore();
                $url = '';

                if (in_array($this->order->getState(), ['processing', 'complete'])) {
                    $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
                }

                if (in_array($this->order->getState(), ['canceled', 'closed'])) {
                    $returnUrl = $this->urlBuilder->setScope($this->storeManager->getStore()->getStoreId());
                    $url = $returnUrl->getRouteUrl('buckaroo/redirect/process') . '?form_key=' . $this->formKey->getFormKey();
                    $extraData = [
                        'brq_invoicenumber' => $params['orderId'],
                        'brq_ordernumber' => $params['orderId'],
                        'brq_statuscode' => $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED'),
                    ];

                    $url = $url . '&'. http_build_query($extraData);
                }

                $response = ['success' => 'true', 'redirect' => $url];
            }
        }

        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData($response);
    }
}
