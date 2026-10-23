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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Sales\Quote;

use Buckaroo\Magento2\Logging\Log;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Makes the Second Chance cart the shopper's only active cart, without losing what they added since.
 *
 * The shopper's current carts are the active carts of the order's customer, or for a guest order the guest
 * cart in the session. Products in them that the order does not cover are added to the restored cart, the way
 * Magento's reorder adds an order to the current cart. A cart restored earlier by a Second Chance link (its
 * reserved order ID is "<existing order>-N") is not merged: that order can be restored again with its own link.
 * All of them are deactivated once the restored cart is saved, so Magento cannot load one of them instead.
 */
class SecondChanceCartMerger
{
    /**
     * Buy request fields that tell two cart lines of the same product apart
     */
    private const OPTION_FIELDS = [
        'super_attribute',
        'options',
        'bundle_option',
        'bundle_option_qty',
        'links',
        'super_group',
    ];

    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Log
     */
    private $logger;

    /**
     * @param QuoteCollectionFactory     $quoteCollectionFactory
     * @param OrderCollectionFactory     $orderCollectionFactory
     * @param CartRepositoryInterface    $cartRepository
     * @param ProductRepositoryInterface $productRepository
     * @param Log                        $logger
     */
    public function __construct(
        QuoteCollectionFactory $quoteCollectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        Log $logger
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Add the products of the shopper's current carts that the order does not cover to the restored cart
     *
     * @param Quote          $restoredQuote  Holds the order's products, not saved yet
     * @param Order          $order
     * @param int            $sessionQuoteId The cart in the session before it is replaced
     * @return Quote[] The current carts, to deactivate once the restored cart is saved
     */
    public function mergeInto(Quote $restoredQuote, Order $order, int $sessionQuoteId): array
    {
        $currentCarts = $this->getCurrentCarts($order, $sessionQuoteId);
        if (empty($currentCarts)) {
            return [];
        }

        $restoredCartIds = $this->getCartsRestoredBySecondChance($currentCarts);
        $orderedQty = $this->getOrderedQtyByLine($order);

        foreach ($currentCarts as $cart) {
            if (in_array((int) $cart->getId(), $restoredCartIds, true)) {
                continue;
            }
            foreach ($cart->getAllVisibleItems() as $item) {
                $key = $this->getLineKey((int) $item->getProductId(), $item->getBuyRequest());
                $coveredQty = min((float) ($orderedQty[$key] ?? 0), (float) $item->getQty());
                $orderedQty[$key] = ($orderedQty[$key] ?? 0) - $coveredQty;

                $extraQty = (float) $item->getQty() - $coveredQty;
                if ($extraQty > 0) {
                    $this->addLine($restoredQuote, $item, $extraQty, (int) $order->getStoreId());
                }
            }
        }

        return $currentCarts;
    }

    /**
     * Deactivate the shopper's previous carts
     *
     * @param Quote[] $carts
     * @return void
     */
    public function deactivate(array $carts): void
    {
        foreach ($carts as $cart) {
            $cart->setIsActive(false);
            $this->cartRepository->save($cart);
            $this->logger->addDebug(__METHOD__ . '|deactivated|' . $cart->getId());
        }
    }

    /**
     * Get the active carts of the order's customer, or the guest cart in the session for a guest order
     *
     * @param Order $order
     * @param int   $sessionQuoteId
     * @return Quote[]
     */
    private function getCurrentCarts(Order $order, int $sessionQuoteId): array
    {
        $customerId = (int) $order->getCustomerId();
        if ($customerId > 0) {
            $collection = $this->quoteCollectionFactory->create();
            $collection->addFieldToFilter('customer_id', ['eq' => $customerId])
                ->addFieldToFilter('store_id', ['eq' => (int) $order->getStoreId()])
                ->addFieldToFilter('is_active', ['eq' => 1]);

            return array_values(array_filter($collection->getItems(), function ($cart) {
                return $cart instanceof Quote;
            }));
        }

        if ($sessionQuoteId <= 0) {
            return [];
        }

        try {
            $cart = $this->cartRepository->get($sessionQuoteId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        // Only the guest's own cart: a cart that belongs to an account stays with that account
        $isGuestCart = $cart instanceof Quote
            && $cart->getIsActive()
            && !$cart->getCustomerId()
            && (int) $cart->getStoreId() === (int) $order->getStoreId();

        return $isGuestCart ? [$cart] : [];
    }

    /**
     * Get the IDs of the carts that a Second Chance link restored for another order
     *
     * @param Quote[] $carts
     * @return int[]
     */
    private function getCartsRestoredBySecondChance(array $carts): array
    {
        $baseIdByCart = [];
        foreach ($carts as $cart) {
            if (preg_match('/^(.+)-\d+$/', (string) $cart->getReservedOrderId(), $matches)) {
                $baseIdByCart[(int) $cart->getId()] = $matches[1];
            }
        }
        if (empty($baseIdByCart)) {
            return [];
        }

        // An increment ID format may itself contain "-<digits>"; only an existing order makes it a restore
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('increment_id', ['in' => array_values(array_unique($baseIdByCart))]);
        $existingOrderIds = $collection->getColumnValues('increment_id');

        return array_keys(array_intersect($baseIdByCart, $existingOrderIds));
    }

    /**
     * Get the ordered quantity per line of the order
     *
     * @param Order $order
     * @return array<string, float>
     */
    private function getOrderedQtyByLine(Order $order): array
    {
        $orderedQty = [];
        foreach ($order->getAllVisibleItems() as $orderItem) {
            $key = $this->getLineKey((int) $orderItem->getProductId(), $orderItem->getBuyRequest());
            $orderedQty[$key] = ($orderedQty[$key] ?? 0) + (float) $orderItem->getQtyOrdered();
        }

        return $orderedQty;
    }

    /**
     * Identify a line by its product and the options that make it a separate cart line
     *
     * @param int        $productId
     * @param DataObject $buyRequest
     * @return string
     */
    private function getLineKey(int $productId, DataObject $buyRequest): string
    {
        $options = array_intersect_key($buyRequest->getData(), array_flip(self::OPTION_FIELDS));

        return $productId . '|' . json_encode($this->normalize($options));
    }

    /**
     * Sort keys and cast values to strings, so equal options from a cart and an order compare equal
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalize($value)
    {
        if (!is_array($value)) {
            return (string) $value;
        }
        ksort($value);

        return array_map([$this, 'normalize'], $value);
    }

    /**
     * Add a quantity of a cart line to the restored cart
     *
     * @param Quote      $restoredQuote
     * @param Quote\Item $item
     * @param float      $qty
     * @param int        $storeId
     * @return void
     */
    private function addLine(Quote $restoredQuote, $item, float $qty, int $storeId): void
    {
        try {
            $product = $this->productRepository->getById((int) $item->getProductId(), false, $storeId);
            if (!$product instanceof Product) {
                return;
            }
            $product = clone $product;
            $product->setCustomOptions([]);
            $buyRequest = new DataObject($item->getBuyRequest()->getData());
            $buyRequest->setQty($qty);

            // addProduct() returns the reason as a string when it cannot add the product
            $result = $restoredQuote->addProduct($product, $buyRequest);
            if (is_string($result)) {
                throw new LocalizedException(__($result));
            }
        } catch (\Exception $e) {
            $this->logger->addError('Second Chance: could not keep a cart product: ' . $e->getMessage());
        }
    }
}
