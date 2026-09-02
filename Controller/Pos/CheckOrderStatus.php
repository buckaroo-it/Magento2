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

namespace Buckaroo\Magento2\Controller\Pos;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\BuckarooStatusCode;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CheckOrderStatus extends Action implements HttpPostActionInterface
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
     * @var Session
     */
    private $customerSession;

    /**
     * @param Context               $context
     * @param Order                 $order
     * @param JsonFactory           $resultJsonFactory
     * @param Factory               $configProviderFactory
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface          $urlBuilder
     * @param FormKey               $formKey
     * @param Session               $customerSession
     * @param CheckoutSession       $checkoutSession
     *
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
        Session $customerSession,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accountConfig = $configProviderFactory->get('account');
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * Process action
     *
     * @throws \Exception
     *
     * @return Json
     */
    public function execute()
    {
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
                    $url = $this->urlBuilder->getRouteUrl(
                        'buckaroo/redirect/process',
                        ['_scope' => $this->storeManager->getStore()->getStoreId()]
                    ) . '?form_key=' . $this->formKey->getFormKey();

                    $extraData = [
                        'brq_invoicenumber' => $params['orderId'],
                        'brq_ordernumber'   => $params['orderId'],
                        'brq_statuscode'    => BuckarooStatusCode::ORDER_FAILED,
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

    /**
     * Verify the loaded order belongs to the current visitor.
     *
     * A logged-in customer must own the order; for guest orders the order must be the
     * one this session just placed. Comparing the customer ids alone is not enough:
     * both are null for a guest order in an anonymous session, which would expose the
     * state of any order whose increment id is guessed.
     *
     * @return bool
     */
    private function isOrderOwnedByCurrentVisitor(): bool
    {
        $sessionCustomerId = $this->customerSession->getCustomerId();

        if ($sessionCustomerId !== null && $this->order->getCustomerId() !== null) {
            return (int)$sessionCustomerId === (int)$this->order->getCustomerId();
        }

        return (string)$this->checkoutSession->getLastRealOrderId() === (string)$this->order->getIncrementId();
    }
}
