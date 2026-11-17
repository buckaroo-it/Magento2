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
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
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
        Session $customerSession,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accountConfig = $configProviderFactory->get('account');
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
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
                    $url = $this->getStoreUrl(
                        (string)($this->accountConfig->getSuccessRedirect($store) ?: 'checkout/onepage/success'),
                        (int)$store->getId()
                    );
                }

                if (in_array($this->order->getState(), ['canceled', 'closed'])) {
                    // The cart was converted into this order; give it back so the customer can try again
                    $this->checkoutSession->restoreQuote();
                    $this->messageManager->addErrorMessage(
                        // phpcs:ignore Generic.Files.LineLength.TooLong -- one literal so the phrase stays translatable
                        __('Unfortunately an error occurred while processing your payment. Please try again. If this error persists, please choose a different payment method.')
                    );
                    $url = $this->getStoreUrl(
                        (string)($this->accountConfig->getFailureRedirect($store) ?: 'checkout/cart'),
                        (int)$store->getId()
                    );
                }

                $response = ['success' => 'true', 'redirect' => $url];
            }
        }

        $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }

    /**
     * Build a URL in the order's store from a configured path, keeping a #fragment (e.g. checkout#payment)
     *
     * @param string $path
     * @param int    $storeId
     *
     * @return string
     */
    private function getStoreUrl(string $path, int $storeId): string
    {
        [$route, $fragment] = array_pad(explode('#', $path, 2), 2, null);
        $url = $this->urlBuilder->getUrl(trim($route, '/'), ['_scope' => $storeId]);

        return $fragment !== null ? rtrim($url, '/') . '#' . $fragment : $url;
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
