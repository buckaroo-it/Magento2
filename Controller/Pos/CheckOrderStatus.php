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

namespace Buckaroo\Magento2\Controller\Pos;

use Buckaroo\Magento2\Logging\Log;

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

    /**
     * @param \Magento\Framework\App\Action\Context               $context
     * @param Log                                                 $logger
     * @param \Magento\Sales\Model\Order                          $order
     *
     * @throws \Buckaroo\Magento2\Exception
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        Log $logger,
        \Magento\Sales\Model\Order $order,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Buckaroo\Magento2\Model\ConfigProvider\Factory $configProviderFactory
    ) {
        parent::__construct($context);
        $this->logger             = $logger;
        $this->order              = $order;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accountConfig = $configProviderFactory->get('account');
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
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
                    $url = $store->getBaseUrl() . '/' . $this->accountConfig->getFailureRedirect($store);
                    $this->messageManager->addErrorMessage(
                        __(
                            'The transaction has not been completed, please try again'
                        )
                    );
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
