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

use Buckaroo\Magento2\Logging\Log;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckOrderStatus extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\Order $order
     */
    protected $order;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
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

    private $customerSession;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param Log $logger
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     * @param \Buckaroo\Magento2\Helper\Data $helper
     * @param \Magento\Customer\Model\Session $customerSession
     * @throws \Buckaroo\Magento2\Exception
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Log $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Buckaroo\Magento2\Helper\Data $helper,
        \Magento\Customer\Model\Session $customerSession
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
        $this->customerSession    = $customerSession;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        $this->logger->addDebug(__METHOD__ . '|1|');
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
