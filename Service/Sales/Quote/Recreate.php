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
namespace Buckaroo\Magento2\Service\Sales\Quote;

use Magento\Checkout\Model\Cart;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class Recreate
{
    /** @var CartRepositoryInterface */
    private $cartRepository;

    /** @var Cart */
    private $cart;

    /** @var \Magento\Checkout\Model\Session */
    private $checkoutSession;
    protected $customerSession;

    protected $quoteFactory;

    protected $productFactory;

    protected $messageManager;
    protected $quoteRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param Cart                    $cart
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        Cart $cart,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->cartRepository  = $cartRepository;
        $this->cart            = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteFactory    = $quoteFactory;
        $this->productFactory  = $productFactory;
        $this->cart            = $cart;
        $this->quoteRepository = $quoteRepository;
        $this->messageManager  = $messageManager;
    }

    /**
     * @param Order $order
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function recreate($order = false, $newQuote = false)
    {
        try {
            $quote = ($order != false) ? $this->quoteFactory->create()->load($order->getQuoteId()) : $newQuote;
            $quote->setIsActive(true);
            $quote->setTriggerRecollect('1');
            $quote->setReservedOrderId(null);
            $quote->setBuckarooFee(null);
            $quote->setBaseBuckarooFee(null);
            $quote->setBuckarooFeeTaxAmount(null);
            $quote->setBuckarooFeeBaseTaxAmount(null);
            $quote->setBuckarooFeeInclTax(null);
            $quote->setBaseBuckarooFeeInclTax(null);
            $this->cart->setQuote($quote)->save();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //No such entity
        }
    }

    public function recreateById($quoteId)
    {
        try {
            $quote = $this->quoteFactory->create()->load($quoteId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        if ($quote->getId()) {
            if ($items = $quote->getAllVisibleItems()) {
                foreach ($items as $item) {
                    $productId = $item->getProductId();
                    $product   = $this->productFactory->create()->load($productId);

                    $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

                    $info        = $options['info_buyRequest'];
                    $info['qty'] = $item->getQty();
                    $requestInfo = new \Magento\Framework\DataObject();
                    $requestInfo->setData($info);

                    try {
                        $this->cart->addProduct($product, $requestInfo);
                    } catch (\Exception $e) {
                        $this->messageManager->addErrorMessage($e->getMessage());
                    }
                }
            }

            $this->cart->setQuote($quote)->save();
            $this->checkoutSession->setQuoteId($quote->getId());
            $this->checkoutSession->getQuote()->collectTotals()->save();
        }
    }

    public function duplicate($order)
    {
        $oldQuote = $this->quoteFactory->create()->load($order->getQuoteId());
        $quote    = $this->quoteFactory->create();
        $quote->merge($oldQuote)->save();
        $this->recreate(false, $quote);
        return $quote;
    }
}
