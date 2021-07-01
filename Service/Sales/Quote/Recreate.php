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

    protected $quoteFactory;

    protected $productFactory;

    protected $messageManager;
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param Cart                    $cart
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->cartRepository  = $cartRepository;
        $this->cart            = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->quoteFactory    = $quoteFactory;
        $this->productFactory  = $productFactory;
        $this->cart            = $cart;
        $this->messageManager  = $messageManager;
    }

    /**
     * @param Order $order
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function recreate($order)
    {
        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get($order->getQuoteId());
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

    public function duplicate($order)
    {
        $quote = $this->quoteFactory->create()->load($order->getQuoteId());
        $items = $quote->getAllVisibleItems();
        foreach ($items as $item) {
            $productId = $item->getProductId();
            $_product  = $this->productFactory->create()->load($productId);

            $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());

            $info     = $options['info_buyRequest'];
            $request1 = new \Magento\Framework\DataObject();
            $request1->setData($info);

            try {
                $this->cart->addProduct($_product, $request1);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        $this->cart->save();
        $this->cart->setQuote($quote);
        $this->checkoutSession->setQuoteId($quote->getId());
        $this->cart->save();
    }
}
