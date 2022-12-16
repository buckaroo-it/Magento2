<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Cart\Data\CartItem;
use Magento\Quote\Model\Cart\BuyRequest\BuyRequestBuilder;
use Buckaroo\Magento2\Model\Applepay as ApplepayModel;
use Magento\Quote\Model\Quote\AddressFactory as BaseQuoteAddressFactory;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\Quote\Model\QuoteRepository;
use Buckaroo\Magento2\Service\Applepay\ShippingMethod as AppleShippingMethod;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Add
{
    private CartRepositoryInterface $cartRepository;
    private $maskedQuoteIdToQuoteId;
    private ProductRepositoryInterface $productRepository;
    private BuyRequestBuilder $requestBuilder;
    private ApplepayModel $applepayModel;
    private BaseQuoteAddressFactory $quoteAddressFactory;
    private ShippingAddressManagementInterface $shippingAddressManagement;
    private ShippingMethod $appleShippingMethod;
    private \Magento\Checkout\Model\Session $checkoutSession;

    /**
     * @var QuoteRepository|mixed
     */
    private $quoteRepository;


    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CartInterface $cart
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param ProductRepositoryInterface $productRepository
     * @param BuyRequestBuilder $requestBuilder
     * @param ApplepayModel $applepayModel
     * @param BaseQuoteAddressFactory $quoteAddressFactory
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param QuoteRepository|null $quoteRepository
     * @param ShippingMethod $appleShippingMethod
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        CartRepositoryInterface            $cartRepository,
        MaskedQuoteIdToQuoteIdInterface    $maskedQuoteIdToQuoteId,
        ProductRepositoryInterface         $productRepository,
        BuyRequestBuilder                  $requestBuilder,
        ApplepayModel                      $applepayModel,
        BaseQuoteAddressFactory            $quoteAddressFactory,
        ShippingAddressManagementInterface $shippingAddressManagement,
        AppleShippingMethod                $appleShippingMethod,
        \Magento\Checkout\Model\Session    $checkoutSession,
        QuoteRepository                    $quoteRepository = null
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->productRepository = $productRepository;
        $this->requestBuilder = $requestBuilder;
        $this->applepayModel = $applepayModel;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->appleShippingMethod = $appleShippingMethod;
        $this->checkoutSession = $checkoutSession;

        $this->quoteRepository = $quoteRepository
        ?? ObjectManager::getInstance()->get(QuoteRepository::class);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function process($request)
    {
        $cartHash = $request->getParam('id');

        if ($cartHash) {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
            $cart = $this->cartRepository->get($cartId);
        } else {
            $cart = $this->checkoutSession->getQuote();
        }

        $product = $request->getParam('product');
        $cart->removeAllItems();

        try {
            $productToBeAdded = $this->productRepository->getById($product['id']);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Could not find a product with ID "%id"', ['id' => $product['id']]));
        }

        $cartItem = new CartItem(
            $productToBeAdded->getSku(),
            $product['qty']
        );

        if (isset($product['selected_options'])) {
            $cartItem->setSelectedOptions($product['selected_options']);
        }

        $cart->addProduct($productToBeAdded, $this->requestBuilder->build($cartItem));
        $this->cartRepository->save($cart);

        $wallet = $request->getParam('wallet');
        $shippingAddressData = $this->applepayModel->processAddressFromWallet($wallet, 'shipping');

        /**
         * @var $shippingAddress \Magento\Quote\Model\Quote\Address
         */
        $shippingAddress = $this->quoteAddressFactory->create();
        $shippingAddress->addData($shippingAddressData);

        $errors = $shippingAddress->validate();
        if (is_array($errors)) {
            return ['success' => 'false', 'error' => $errors];
        }

        try {
            $this->shippingAddressManagement->assign($cart->getId(), $shippingAddress);
        } catch (\Exception $e) {
            return ['success' => 'false', 'error' => $e->getMessage()];
        }
        $this->quoteRepository->save($cart);
        $shippingMethodsResult = [];
        //this delivery address is already assigned to the cart
        $shippingMethods = $this->appleShippingMethod->getAvailableMethods($cart);
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethodsResult[] = [
                'carrier_title' => $shippingMethod['carrier_title'],
                'price_incl_tax' => round($shippingMethod['amount'], 2),
                'method_code' => $shippingMethod['carrier_code'] . '_' .  $shippingMethod['method_code'],
                'method_title' => $shippingMethod['method_title'],
            ];
        }
        $cart->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
        $cart->setTotalsCollectedFlag(false);
        $cart->collectTotals();
        $totals = $this->gatherTotals($cart->getShippingAddress(), $cart->getTotals());
        if ($cart->getSubtotal() != $cart->getSubtotalWithDiscount()) {
            $totals['discount'] = round($cart->getSubtotalWithDiscount() - $cart->getSubtotal(), 2);
        }

        return [
            'shipping_methods' => $shippingMethodsResult,
            'totals' => $totals
        ];
    }
    public function gatherTotals($address, $quoteTotals)
    {
        $totals = [
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $address->getData('shipping_incl_tax'),
            'grand_total' => $quoteTotals['grand_total']->getValue()
        ];

        return $totals;
    }
}
