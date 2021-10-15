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
use Magento\Quote\Model\ResourceModel\Quote\Address as QuoteAddressResource;
use Buckaroo\Magento2\Logging\Log;

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

    protected $quoteManagement;
    private $quoteAddressResource;

    protected $logger;

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
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        QuoteAddressResource $quoteAddressResource,
        Log $logger
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
        $this->quoteManagement = $quoteManagement;
        $this->quoteAddressResource = $quoteAddressResource;
        $this->logger          = $logger;
    }

    /**
     * @param Order $order
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function recreate($order = false, $newQuote = false)
    {
        // @codingStandardsIgnoreStart
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
            if ($this->cart->setQuote($quote)->save()) {
                return true;
            }
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //No such entity
        }
        // @codingStandardsIgnoreEnd
        return false;
    }

    public function recreateById($quoteId)
    {
        try {
            $oldQuote = $this->quoteFactory->create()->load($quoteId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        if ($oldQuote->getId()) {
            $emptyQuoteId = $this->quoteManagement->createEmptyCart();

            try {
                $quote = $this->quoteFactory->create()->load($emptyQuoteId);
                $quote->merge($oldQuote)->save();
            } catch (\Exception $e) {
                $this->logger->addError($e->getMessage());
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            $this->recreate(false, $quote);

            if ($newIncrementId = $this->customerSession->getSecondChanceNewIncrementId()) {
                $this->customerSession->setSecondChanceNewIncrementId(false);
                $this->checkoutSession->getQuote()->setReservedOrderId($newIncrementId);
                $this->checkoutSession->getQuote()->save();
                $quote->setReservedOrderId($newIncrementId)->save();
            }

            if ($email = $oldQuote->getBillingAddress()->getEmail()) {
                $quote->setCustomerEmail($email);
            }

            $quote->setCustomerIsGuest(true);
            if ($customer = $this->customerSession->getCustomer()) {
                $quote->setCustomerId($customer->getId());
                $quote->setCustomerGroupId($customer->getGroupId());
                $quote->setCustomerIsGuest(false);
            }

            $quote->collectTotals();

            $this->checkoutSession->setQuoteId($quote->getId());

            if ($items = $oldQuote->getAllVisibleItems()) {
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

            $this->checkoutSession->getQuote()->collectTotals()->save();
            $this->cart->setQuote($quote)->save();
            $this->cart->saveQuote();
        }
    }

    public function duplicate($order)
    {
        $oldQuote = $this->quoteFactory->create()->load($order->getQuoteId());
        $emptyQuoteId = $this->quoteManagement->createEmptyCart();
        $quote = $this->quoteFactory->create()->load($emptyQuoteId);

        if (!$oldQuote->getCustomerIsGuest() && $oldQuote->getCustomerId()) {
            $quote->setCustomerId($oldQuote->getCustomerId());
        }

        $quote->setCustomerEmail($oldQuote->getBillingAddress()->getEmail());
        $quote->setCustomerIsGuest($oldQuote->getCustomerIsGuest());

        if ($customer = $this->customerSession->getCustomer()) {
            $quote->setCustomerId($customer->getId());
            $quote->setCustomerEmail($customer->getEmail());
            $quote->setCustomerFirstname($customer->getFirstname());
            $quote->setCustomerLastname($customer->getLastname());
            $quote->setCustomerGroupId($customer->getGroupId());
            $quote->setCustomerIsGuest(false);
        }

        $quote->setBillingAddress($oldQuote->getBillingAddress());
        $quote->setShippingAddress($oldQuote->getShippingAddress());

        $quote->merge($oldQuote)->save();
        $quote->collectTotals();
        $this->recreate(false, $quote);
        $this->cart->saveQuote();
        $this->checkoutSession->setQuoteId($quote->getId());
        $this->checkoutSession->getQuote()->collectTotals()->save();
        $quote->getShippingAddress()->setShippingMethod($oldQuote->getShippingAddress()->getShippingMethod());
        $this->quoteAddressResource->save($quote->getShippingAddress());
        return $quote;
    }
}
