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
namespace Buckaroo\Magento2\Observer;

use Buckaroo\Magento2\Model\Method\Giftcards;
use Buckaroo\Magento2\Model\Method\Payconiq;

class RestoreQuote implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Account
     */
    protected $accountConfig;

    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceFactory
     */
    protected $secondChanceFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    protected $request;

    protected $mathRandom;

    protected $resultFactory;

    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    private $responseFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $url;

    protected $orderFactory;

    /**
     * @var \Magento\Quote\Model\Quote $quote
     */
    protected $quote;

    protected $quoteFactory;

    protected $productFactory;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;

    protected $_messageManager;

    /**
     * @param \Magento\Checkout\Model\Session\Proxy                $checkoutSession
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account      $accountConfig
     * @param \Buckaroo\Magento2\Model\SecondChanceFactory         $secondChanceFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime          $dateTime
     */
    public function __construct(
        \Magento\Checkout\Model\Session\Proxy $checkoutSession,
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Buckaroo\Magento2\Model\SecondChanceFactory $secondChanceFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Checkout\Model\Cart $cart,
        \Buckaroo\Magento2\Helper\Data $helper,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
    ) {
        $this->checkoutSession     = $checkoutSession;
        $this->accountConfig       = $accountConfig;
        $this->secondChanceFactory = $secondChanceFactory;
        $this->request             = $request;
        $this->mathRandom          = $mathRandom;
        $this->resultFactory       = $resultFactory;
        $this->responseFactory     = $responseFactory;
        $this->url                 = $url;
        $this->orderFactory        = $orderFactory;
        $this->quote               = $quote;
        $this->quoteFactory        = $quoteFactory;
        $this->productFactory      = $productFactory;
        $this->_messageManager     = $messageManager;
        $this->cart                = $cart;
        $this->dateTime            = $dateTime;
        $this->helper              = $helper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($payment = $lastRealOrder->getPayment()) {
            if (strpos($payment->getMethod(), 'buckaroo_magento2') === false) {
                return;
            }
            if (in_array($payment->getMethod(), [Giftcards::PAYMENT_METHOD_CODE, Payconiq::PAYMENT_METHOD_CODE])) {
                return true;
            }
            $order = $payment->getOrder();

            if ($this->accountConfig->getCartKeepAlive($order->getStore())) {
                if ($this->helper->getRestoreQuoteLastOrder() && ($lastRealOrder->getData('state') === 'new' && $lastRealOrder->getData('status') === 'pending') && $payment->getMethodInstance()->usesRedirect) {

                    if ($this->accountConfig->getSecondChance($order->getStore())) {
                        $secondChance = $this->secondChanceFactory->create();
                        $secondChance->setData([
                            'order_id' => $order->getIncrementId(),
                            'token' => $this->mathRandom->getUniqueHash(),
                            'store_id' => $order->getStoreId(),
                            'created_at' => $this->dateTime->gmtDate(),
                        ]);
                        $secondChance->save();

                        $this->duplicateQuote($order);
                    }else{
                        $this->checkoutSession->restoreQuote();
                    }
                    
                }
            }
            $this->helper->setRestoreQuoteLastOrder(false);
        }
        return true;
    }

    public function duplicateQuote($order){
        $quote = $this->quoteFactory->create()->load($order->getQuoteId());
        $items = $quote->getAllVisibleItems();
        foreach ($items as $item) {
            $productId = $item->getProductId();
            $_product = $this->productFactory->create()->load($productId);

            $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

            $info = $options['info_buyRequest'];
            $request1 = new \Magento\Framework\DataObject();
            $request1->setData($info);

            try {
                $this->cart->addProduct($_product, $request1);
            } catch (\Exception $e) {
                $this->_messageManager->addErrorMessage($e->getMessage());
            }
        }

        $this->cart->save();
        $this->cart->setQuote($quote);
        $this->checkoutSession->setQuoteId($quote->getId());
        $this->cart->save();
    }
}
