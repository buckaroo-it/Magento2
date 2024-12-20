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
use Buckaroo\Magento2\Logging\Log as BuckarooLog;

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
     * @var BuckarooLog
     */
    private BuckarooLog $logger;


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
     * @param BuckarooLog $logger
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
        AppleShippingMethod $appleShippingMethod,
        BuckarooLog $logger

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
        $this->logger = $logger;
    }

    public function process($request)
    {
        $this->logger->addDebug('Add - Process');
        $this->logger->addDebug('Request Variable: '. json_encode($request));

        $cart_hash = $request->getParam('id');

        $this->logger->addDebug('Cart hash Variable: '. json_encode($cart_hash));
        if($cart_hash) {
            $this->logger->addDebug('Add - Process - If cart hash');

            $cartId = $this->maskedQuoteIdToQuoteId->execute($cart_hash);
            $this->logger->addDebug('Cart Id Variable: '. json_encode($cartId));
            $cart = $this->cartRepository->get($cartId);
            $this->logger->addDebug('Cart Variable (If): '. json_encode($cart));

        } else {
            $this->logger->addDebug('Add - Process - Else cart hash');
            $checkoutSession = ObjectManager::getInstance()->get(\Magento\Checkout\Model\Session::class);
            $cart = $checkoutSession->getQuote();
            $this->logger->addDebug('Cart Variable (Else): '. json_encode($cart->getId()));
        }

        $product = $request->getParam('product');
        $this->logger->addDebug('Add - Process - Product Variable: '. json_encode($product));

        // Check if product data is present and valid
        //if (!$product || !is_array($product) || !isset($product['id']) || !is_numeric($product['id'])) {
        //    throw new \Exception('Product data is missing or invalid.');
        //}


        $this->logger->addDebug('Cart Variable before: '. json_encode($cart->getAllItems()));
        $cart->removeAllItems();
        $this->logger->addDebug('Cart Variable after: '. json_encode($cart->getAllItems()));

        try {
            $productToBeAdded = $this->productRepository->getById(15);
            $this->logger->addDebug('Product to be added Variable: '. json_encode($productToBeAdded));
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Could not find a product with ID "%id"', ['id' => 15]));
        }

        $cartItem = new CartItem(
            $productToBeAdded->getSku(),
            1
        );

        $this->logger->addDebug('Cart Item Variable: '. json_encode($cartItem));
        if(isset($product['selected_options'])) {
            $cartItem->setSelectedOptions($product['selected_options']);
        }

        $cart->addProduct($productToBeAdded, $this->requestBuilder->build($cartItem));
        $this->cartRepository->save($cart);

        $this->logger->addDebug('Cart Variable after added product: '. json_encode($cart->getAllItems()));

        $wallet = $request->getParam('wallet');

        $this->logger->addDebug('Wallet Variable: '. json_encode($wallet));

        $shippingMethodsResult = [];
        if (!$cart->getIsVirtual()) {
            $this->logger->addDebug('Add - Process - If cart is not virtual');
            $shippingAddressData = $this->applepayModel->processAddressFromWallet($wallet, 'shipping');
            $this->logger->addDebug('Shipping Address Data Variable: '. json_encode($shippingAddressData));


            $shippingAddress = $this->quoteAddressFactory->create();
            $shippingAddress->addData($shippingAddressData);

            $this->logger->addDebug('Shipping Address Variable: '. json_encode($shippingAddress));

            $errors = $shippingAddress->validate();

            $this->logger->addDebug('Errors Variable: '. json_encode($errors));

            try {
                $this->shippingAddressManagement->assign($cart->getId(), $shippingAddress);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
                // phpcs:ignore Magento2.Security.LanguageConstruct.DirectOutput
                //echo $e->getMessage();
            }
            $this->quoteRepository->save($cart);
            //this delivery address is already assigned to the cart
            $this->logger->addDebug('Cart Variable (line 209): '. json_encode($cart));
            $this->logger->addDebug('Request param shipping method: '. json_encode($request->getParam('shippingMethod')));
            $this->logger->addDebug('Cart shipping addressss: '. json_encode($cart->getShippingAddress()));
            $this->logger->addDebug('Cart shipping address methodsss: '. json_encode($cart->getShippingAddress()->getGroupedAllShippingRates()));
            try {
                $shippingMethods = $this->appleShippingMethod->getAvailableMethods($cart);
            } catch (\Exception $e) {
                throw new \Exception(__('Unable to retrieve shipping methods.'));
            }

            $this->logger->addDebug('Shipping Methods Variable: '. json_encode($shippingMethods));


            foreach ($shippingMethods as $method) {
                $shippingMethodsResult[] = [
                    'carrier_title' => $method['carrier_title'],
                    'price_incl_tax' => round($method['amount']['value'], 2),
                    'method_code' => $method['carrier_code'] . '_' .  $method['method_code'],
                    'method_title' => $method['method_title'],
                ];
            }
            $this->logger->addDebug('Shipping Methods Result Variable: '. json_encode($shippingMethodsResult));

            if (!empty($shippingMethodsResult)) {
                // Set the first available shipping method
                $cart->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
            } else {
                throw new \Exception(__('No shipping methods are available for the provided address.'));
            }
        }
        $cart->setTotalsCollectedFlag(false);
        $cart->collectTotals();
        $totals = $this->gatherTotals($cart->getShippingAddress(), $cart->getTotals());
        if ($cart->getSubtotal() != $cart->getSubtotalWithDiscount()) {
            $totals['discount'] = round($cart->getSubtotalWithDiscount() - $cart->getSubtotal(), 2);
        }

        $this->logger->addDebug('Cart Variable (243): '. json_encode($cart));
        $this->logger->addDebug('Totals Variable: '. json_encode($totals));

        $this->quoteRepository->save($cart);
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
