<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Service\AddProductToCartService;
use Buckaroo\Magento2\Model\Service\ApplePayFormatData;
use Buckaroo\Magento2\Model\Service\ExpressMethodsException;
use Buckaroo\Magento2\Model\Service\QuoteAddressService;
use Buckaroo\Magento2\Model\Service\QuoteException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\DataObjectFactory;
use Buckaroo\Magento2\Model\Applepay as ApplepayModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory as BaseQuoteAddressFactory;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Magento\Quote\Model\QuoteRepository;
use Buckaroo\Magento2\Service\Applepay\ShippingMethod as AppleShippingMethod;
use Buckaroo\Magento2\Model\Service\QuoteService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Add
{
    private CartRepositoryInterface $cartRepository;
    private $maskedQuoteIdToQuoteId;
    private ProductRepositoryInterface $productRepository;
    private DataObjectFactory $dataObjectFactory;
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
     * @var Log $logging
     */
    public $logging;

    /**
     * @var QuoteService
     */
    private $quoteService;

    /**
     * @var AddProductToCartService
     */
    private $addProductToCartService;

    /**
     * @var QuoteAddressService
     */
    private $quoteAddressService;

    /**
     * @var ApplePayFormatData
     */
    private ApplePayFormatData $applePayFormatData;


    /**
     * @param CartRepositoryInterface $cartRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param ProductRepositoryInterface $productRepository
     * @param DataObjectFactory $dataObjectFactory
     * @param ApplepayModel $applepayModel
     * @param BaseQuoteAddressFactory $quoteAddressFactory
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param ShippingMethod $appleShippingMethod
     * @param Session $checkoutSession
     * @param Log $logging
     * @param QuoteRepository|null $quoteRepository
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        ProductRepositoryInterface $productRepository,
        DataObjectFactory $dataObjectFactory,
        ApplepayModel $applepayModel,
        BaseQuoteAddressFactory $quoteAddressFactory,
        ShippingAddressManagementInterface $shippingAddressManagement,
        AppleShippingMethod $appleShippingMethod,
        \Magento\Checkout\Model\Session $checkoutSession,
        Log $logging,
        QuoteRepository $quoteRepository,
        QuoteService $quoteService,
        AddProductToCartService $addProductToCartService,
        QuoteAddressService $quoteAddressService,
        ApplePayFormatData $applePayFormatData
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->productRepository = $productRepository;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->applepayModel = $applepayModel;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->appleShippingMethod = $appleShippingMethod;
        $this->checkoutSession = $checkoutSession;
        $this->logging = $logging;
        $this->quoteRepository = $quoteRepository;
        $this->quoteService = $quoteService;
        $this->addProductToCartService = $addProductToCartService;
        $this->quoteAddressService = $quoteAddressService;
        $this->applePayFormatData = $applePayFormatData;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws ExpressMethodsException
     */
    public function process($request)
    {
        // Get Cart
        $cartHash = $request->getParam('id');
        $cart = $this->quoteService->getEmptyQuote($cartHash);

        // Add product to cart
        $product = $this->applePayFormatData->getProductObject($request->getParam('product'));
        $cart = $this->addProductToCartService->addProductToCart($product, $cart);

        // Get Shipping Address From Request
        $shippingAddressRequest = $this->applePayFormatData->getShippingAddressObject($request->getParam('wallet'));

        // Add Shipping Address on Quote
        $cart = $this->quoteAddressService->addAddressToQuote($shippingAddressRequest, $cart);
        $cart = $this->quoteAddressService->assignAddressToQuote($cart->getShippingAddress(), $cart);

        // Set Shipping Method
        addFirstShippingMethod($cart->getShippingAddress());

        $shippingMethodsResult = [];
        $this->logging->addDebug(__METHOD__ . '|9.2|');
        //this delivery address is already assigned to the cart
        $shippingMethods = $this->appleShippingMethod->getAvailableMethods($cart);
        $this->logging->addDebug(__METHOD__ . '|9.3|');
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethodsResult[] = [
                'carrier_title' => $shippingMethod['carrier_title'],
                'price_incl_tax' => round($shippingMethod['amount']['value'], 2),
                'method_code' => $shippingMethod['carrier_code'] . '_' .  $shippingMethod['method_code'],
                'method_title' => $shippingMethod['method_title'],
            ];
        }
        $this->logging->addDebug(__METHOD__ . '|9.4|' . var_export($shippingMethodsResult, true));
        try {
            $cart->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
        } catch (\Exception $e) {
            $this->logging->addDebug(__METHOD__ . '|9.45 exception|' . $e->getMessage());
        }
        $this->logging->addDebug(__METHOD__ . '|9.5|');

        //Set Payment Method



        // Calculate Quote Totals
        $cart->setTotalsCollectedFlag(false);
        $cart->collectTotals();
        $this->logging->addDebug(__METHOD__ . '|9.6|');
        $this->logging->addDebug(__METHOD__ . '|10|');
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

    /**
     * Get checkout quote instance by current session
     *
     * @param int|string $cartHash
     * @return Quote
     */
    public function getCart(int|string $cartHash): Quote
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        if ($cartHash) {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
            $cart = $this->cartRepository->get($cartId);
        } else {
            $cart = $this->checkoutSession->getQuote();
        }

        return $cart;
    }

    /**
     * Add Product Selected to cart
     *
     * @param array $product
     * @return Quote
     */
    public function addProductToCart($product, $cart): Quote
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        try {
            $productToBeAdded = $this->productRepository->getById($product['id']);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(__('Could not find a product with ID "%id"', ['id' => $product['id']]));
        }

        $this->logging->addDebug(__METHOD__ . '|3|');

        $buyRequest = $this->dataObjectFactory->create(
            ['data' => [
                'product' => $product['id'],
                'selected_configurable_option' => '',
                'related_product' => '',
                'item' => $product['id'],
                'super_attribute' => $product['selected_options'] ?? '',
                'qty' => $product['qty'],
            ]]
        );

        $cart->addProduct($productToBeAdded, $buyRequest);
        $this->cartRepository->save($cart);
    }
}
