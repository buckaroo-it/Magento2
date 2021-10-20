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
use Buckaroo\Magento2\Service\Sales\Quote\Recreate as QuoteRecreate;

class RestoreQuote implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    private $customerSession;

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

    private $quoteRecreate;

    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceRepository
     */
    protected $secondChanceRepository;

    protected $quoteRepository;

    /**
     * @param \Magento\Checkout\Model\Session                      $checkoutSession
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account      $accountConfig
     * @param \Buckaroo\Magento2\Model\SecondChanceFactory         $secondChanceFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime          $dateTime
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
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
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        QuoteRecreate $quoteRecreate,
        \Buckaroo\Magento2\Model\SecondChanceRepository $secondChanceRepository,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession     = $checkoutSession;
        $this->customerSession     = $customerSession;
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
        $this->quoteRecreate       = $quoteRecreate;
        $this->secondChanceRepository = $secondChanceRepository;
        $this->quoteRepository     = $quoteRepository;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->helper->addDebug(__METHOD__ . '|RestoreQuote|1|');

        if ($quoteId = $this->customerSession->getSecondChanceRecreate()) {
            $this->quoteRecreate->recreateById($quoteId);
            $this->customerSession->setSecondChanceRecreate(false);
            return true;
        }

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
                $this->helper->addDebug(__METHOD__ . '|cartKeepAlive enabled|');

                if ($this->checkoutSession->getQuote()->getId()
                    && ($quote = $this->quoteRepository->getActive($this->checkoutSession->getQuote()->getId()))
                ) {
                    if ($shippingAddress = $quote->getShippingAddress()) {
                        if (!$shippingAddress->getShippingMethod()) {
                            $shippingAddress->load($shippingAddress->getAddressId());
                        }
                    }
                }

                if ($this->helper->getRestoreQuoteLastOrder()
                    && ($lastRealOrder->getData('state') === 'new')
                    && ($lastRealOrder->getData('status') === 'pending')
                    && $payment->getMethodInstance()->usesRedirect
                ) {
                    $this->helper->addDebug(__METHOD__ . '|restoreQuote for cartKeepAlive|');
                    $this->checkoutSession->restoreQuote();
                }
            }
            $this->helper->addDebug(__METHOD__ . '|setRestoreQuoteLastOrder for cartKeepAlive|');
            $this->helper->setRestoreQuoteLastOrder(false);
        }
        $this->helper->addDebug(__METHOD__ . '|RestoreQuote|end|');
        return true;
    }
}
