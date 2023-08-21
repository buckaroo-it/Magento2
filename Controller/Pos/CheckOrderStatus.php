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

namespace Buckaroo\Magento2\Controller\Pos;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckOrderStatus extends Action
{
    /**
     * @var Order $order
     */
    protected $order;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ConfigProviderInterface
     */
    protected $accountConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @param Context $context
     * @param Order $order
     * @param JsonFactory $resultJsonFactory
     * @param Factory $configProviderFactory
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Data $helper
     * @param Session $customerSession
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Order $order,
        JsonFactory $resultJsonFactory,
        Factory $configProviderFactory,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        Data $helper,
        Session $customerSession
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accountConfig = $configProviderFactory->get('account');
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->helper = $helper;
        $this->customerSession = $customerSession;
    }

    /**
     * Process action
     *
     * @return Json
     * @throws \Exception
     */
    public function execute()
    {
        $response = ['success' => 'false', 'redirect' => ''];

        if (($params = $this->getRequest()->getParams()) && !empty($params['orderId'])) {
            $this->order->loadByIncrementId($params['orderId']);
            if ($this->customerSession->getCustomerId() === $this->order->getCustomerId() && $this->order->getId()) {
                $store = $this->order->getStore();
                $url = '';

                if (in_array($this->order->getState(), ['processing', 'complete'])) {
                    $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
                }

                if (in_array($this->order->getState(), ['canceled', 'closed'])) {
                    $returnUrl = $this->urlBuilder->setScope($this->storeManager->getStore()->getStoreId());
                    $url = $returnUrl->getRouteUrl('buckaroo/redirect/process')
                        . '?form_key=' . $this->formKey->getFormKey();
                    $extraData = [
                        'brq_invoicenumber' => $params['orderId'],
                        'brq_ordernumber' => $params['orderId'],
                        'brq_statuscode' => $this->helper->getStatusCode('BUCKAROO_MAGENTO2_ORDER_FAILED'),
                    ];

                    $url = $url . '&' . http_build_query($extraData);
                }

                $response = ['success' => 'true', 'redirect' => $url];
            }
        }

        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}
