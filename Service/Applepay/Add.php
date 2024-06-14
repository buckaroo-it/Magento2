<?php

namespace Buckaroo\Magento2\Service\Applepay;

use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Cart\Data\CartItem;
use Magento\Quote\Model\Cart\BuyRequest\BuyRequestBuilder;
use Buckaroo\Magento2\Model\Applepay as ApplepayModel;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\AddressFactory as BaseQuoteAddressFactory;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\Quote\Model\QuoteRepository;
use Buckaroo\Magento2\Service\Applepay\ShippingMethod as AppleShippingMethod;

class Add
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CartInterface
     */
    private $cart;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var BuyRequestBuilder
     */
    private BuyRequestBuilder $requestBuilder;

    /**
     * @var ApplepayModel
     */
    private ApplepayModel $applepayModel;

    /**
     * @var QuoteAddressFactory|BaseQuoteAddressFactory
     */
    private $quoteAddressFactory;

    /**
     * @var ShippingAddressManagementInterface
     */
    private ShippingAddressManagementInterface $shippingAddressManagement;

    /**
     * @var QuoteRepository|mixed
     */
    private mixed $quoteRepository;

    /**
     * @var ShippingMethod
     */
    private ShippingMethod $appleShippingMethod;

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
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CartInterface $cart,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        ProductRepositoryInterface $productRepository,
        BuyRequestBuilder $requestBuilder,
        ApplepayModel $applepayModel,
        BaseQuoteAddressFactory $quoteAddressFactory,
        ShippingAddressManagementInterface $shippingAddressManagement,
        QuoteRepository $quoteRepository = null,
        AppleShippingMethod $appleShippingMethod

    ) {
        $this->cartRepository = $cartRepository;
        $this->cart = $cart;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->productRepository = $productRepository;
        $this->requestBuilder = $requestBuilder;
        $this->applepayModel = $applepayModel;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->quoteRepository = $quoteRepository
            ?? ObjectManager::getInstance()->get(QuoteRepository::class);
        $this->appleShippingMethod = $appleShippingMethod;

    }

    public function process($request)
    {
        $cart_hash = $request->getParam('id');
        
        if($cart_hash) {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cart_hash);
            $cart = $this->cartRepository->get($cartId);
        } else {
            $checkoutSession = ObjectManager::getInstance()->get(\Magento\Checkout\Model\Session::class);
            $cart = $checkoutSession->getQuote();
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

        if(isset($product['selected_options'])) {
            $cartItem->setSelectedOptions($product['selected_options']);
        }

        $cart->addProduct($productToBeAdded, $this->requestBuilder->build($cartItem));
        $this->cartRepository->save($cart);
        
        $wallet = $request->getParam('wallet');

        $shippingMethodsResult = [];
        if (!$cart->getIsVirtual()) {
            $shippingAddressData = $this->applepayModel->processAddressFromWallet($wallet, 'shipping');
            
            
            $shippingAddress = $this->quoteAddressFactory->create();
            $shippingAddress->addData($shippingAddressData);

            $errors = $shippingAddress->validate(); 
                    
            try {
                $this->shippingAddressManagement->assign($cart->getId(), $shippingAddress);
            } catch (\Exception $e) {
                // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
                echo $e->getMessage();
            }
            $this->quoteRepository->save($cart);
            //this delivery address is already assigned to the cart
            $shippingMethods = $this->appleShippingMethod->getAvailableMethods( $cart);
            foreach ($shippingMethods as $index => $shippingMethod) {
                $shippingMethodsResult[] = [
                    'carrier_title' => $shippingMethod['carrier_title'],
                    'price_incl_tax' => round($shippingMethod['amount'], 2),
                    'method_code' => $shippingMethod['carrier_code'] . '_' .  $shippingMethod['method_code'],
                    'method_title' => $shippingMethod['method_title'],
                ];
            }
            $cart->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
        }
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
        $shippingTotalInclTax = 0;
        if ($address !== null) {
            $shippingTotalInclTax = $address->getData('shipping_incl_tax');
        }

        return [
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $shippingTotalInclTax,
            'grand_total' => $quoteTotals['grand_total']->getValue()
        ];
    }
}