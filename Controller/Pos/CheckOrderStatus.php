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
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\NoSuchEntityException;
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

    /**
     * Retained for constructor backward-compatibility.
     */
    private $formKey;

    /**
     * Retained for constructor backward-compatibility.
     */
    private $helper;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CustomerSession
     */
    private $customerSession;

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
     * @param CheckoutSession|null   $checkoutSession
     * @param CustomerSession|null   $customerSession
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
        Data $helper,
        ?CheckoutSession $checkoutSession = null,
        ?CustomerSession $customerSession = null
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
        $this->checkoutSession    = $checkoutSession ?? ObjectManager::getInstance()->get(CheckoutSession::class);
        $this->customerSession    = $customerSession ?? ObjectManager::getInstance()->get(CustomerSession::class);
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
            if ($this->order->getId() && $this->isOrderOwnedByCurrentVisitor()) {
                $store = $this->order->getStore();
                $url = '';

                if (in_array($this->order->getState(), ['processing', 'complete'])) {
                    $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
                }

                if (in_array($this->order->getState(), ['canceled', 'closed'])) {
                    $url = $this->handleFailedOrder($store);
                }

                $response = ['success' => 'true', 'redirect' => $url];
            }
        }

        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData($response);
    }

    /**
     * Only the visitor who placed the order may poll its status.
     *
     * @return bool
     */
    private function isOrderOwnedByCurrentVisitor()
    {
        $sessionCustomerId = $this->customerSession->getCustomerId();

        if ($sessionCustomerId !== null && $this->order->getCustomerId() !== null) {
            return (int)$sessionCustomerId === (int)$this->order->getCustomerId();
        }

        return (string)$this->checkoutSession->getLastRealOrderId() === (string)$this->order->getIncrementId();
    }

    /**
     * Give the shopper their cart back and send them to the failure page.
     *
     * This used to hop through buckaroo/redirect/process with unsigned brq_* parameters, which that
     * controller now rejects because it only acts on data signed by Buckaroo.
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     *
     * @return string
     * @throws NoSuchEntityException
     */
    private function handleFailedOrder($store)
    {
        if ((string)$this->checkoutSession->getLastRealOrderId() === (string)$this->order->getIncrementId()) {
            try {
                $this->checkoutSession->restoreQuote();
            } catch (\Throwable $e) {
                $this->logger->addError(__METHOD__ . '|Could not restore the quote: ' . $e->getMessage());
            }
        }

        $this->messageManager->addErrorMessage(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            __('Unfortunately an error occurred while processing your payment. Please try again. If this error persists, please choose a different payment method.')
        );

        $urlBuilder = $this->urlBuilder->setScope($this->storeManager->getStore()->getStoreId());

        if ($this->accountConfig->getFailureRedirectToCheckout($store)) {
            return $urlBuilder->getUrl('checkout', ['_fragment' => 'payment', '_query' => ['bk_e' => 1]]);
        }

        return $urlBuilder->getUrl($this->accountConfig->getFailureRedirect($store));
    }
}
