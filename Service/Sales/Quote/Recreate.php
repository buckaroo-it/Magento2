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

    protected $quoteAddressResource;

    protected $logger;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param Cart                    $cart
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Quote\Model\ResourceModel\Quote\Address $quoteAddressResource,
        \Buckaroo\Magento2\Logging\Log $logger
    ) {
        $this->cartRepository       = $cartRepository;
        $this->cart                 = $cart;
        $this->checkoutSession      = $checkoutSession;
        $this->customerSession      = $customerSession;
        $this->quoteFactory         = $quoteFactory;
        $this->productFactory       = $productFactory;
        $this->cart                 = $cart;
        $this->quoteRepository      = $quoteRepository;
        $this->messageManager       = $messageManager;
        $this->quoteManagement      = $quoteManagement;
        $this->quoteAddressResource = $quoteAddressResource;
        $this->logger               = $logger;
    }

    /**
     * @param Order $order
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function recreate($quote)
    {
        // @codingStandardsIgnoreStart
        try {
            $quote->setIsActive(true);
            $quote->setTriggerRecollect('1');
            $quote->setReservedOrderId(null);
            $quote->setBuckarooFee(null);
            $quote->setBaseBuckarooFee(null);
            $quote->setBuckarooFeeTaxAmount(null);
            $quote->setBuckarooFeeBaseTaxAmount(null);
            $quote->setBuckarooFeeInclTax(null);
            $quote->setBaseBuckarooFeeInclTax(null);
            $quote->save();
            $this->cart->setQuote($quote);
            $this->cart->save();
            return $quote;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            //No such entity
            $this->logger->addError($e->getMessage());
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
            try {
                $quote = $this->quoteFactory->create();
                $quote->merge($oldQuote)->save();
            } catch (\Exception $e) {
                $this->logger->addError($e->getMessage());
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            $quote->setStoreId($oldQuote->getStoreId());
            $quote->getPayment()->setMethod($oldQuote->getPayment()->getMethod());
            $this->cart->setStoreId($oldQuote->getStoreId());
            $this->checkoutSession->setQuoteId($quote->getId());

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

            $this->recreate($quote);

            return $this->additionalMerge($oldQuote, $quote);

        }
    }

    public function duplicate($order)
    {
        $quote = $this->quoteFactory->create();
        try {
            $oldQuote = $this->quoteFactory->create()->load($order->getQuoteId());
            $quote->merge($oldQuote)->save();
        } catch (\Exception $e) {
            $this->logger->addError($e->getMessage());
        }
        $this->recreate($quote);

        return $this->additionalMerge($oldQuote, $quote);
    }

    private function additionalMerge($oldQuote, $quote)
    {
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
        $quote->setBillingAddress($oldQuote->getBillingAddress()->setQuote($quote)->setId($quote->getBillingAddress()->getId()));
        $quote->setShippingAddress($oldQuote->getShippingAddress()->setQuote($quote)->setId($quote->getShippingAddress()->getId()));
        $quote->getShippingAddress()->setShippingMethod($oldQuote->getShippingAddress()->getShippingMethod());
        $this->quoteAddressResource->save($quote->getBillingAddress());
        $this->quoteAddressResource->save($quote->getShippingAddress());

        $this->cart->setQuote($quote);
        $this->cart->save();

        return $quote;
    }

}
