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

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;

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
        \Magento\Checkout\Model\Cart $cart,
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
        $this->cart                = $cart;
        $this->dateTime            = $dateTime;
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
            if (in_array($payment->getMethod(), [Giftcards::PAYMENT_METHOD_CODE])) {
                return true;
            }
            $order = $payment->getOrder();

            if ($this->accountConfig->getCartKeepAlive($order->getStore())) {
                if ((!$this->checkoutSession->getPaymentEnded() || $this->checkoutSession->getPaymentEnded() != $order->getIncrementId()) && $payment->getMethodInstance()->usesRedirect) {

                    $this->checkoutSession->restoreQuote();

                    if ($this->accountConfig->getSecondChance($order->getStore())) {
                        $secondChance = $this->secondChanceFactory->create();
                        $secondChance->setData([
                            'order_id' => $order->getIncrementId(),
                            'token' => $this->mathRandom->getUniqueHash(),
                            'store_id' => $order->getStoreId(),
                            'created_at' => $this->dateTime->gmtDate(),
                        ]);
                        $secondChance->save();
                    }
                    
                }
            }
        }
        return true;
    }
}
