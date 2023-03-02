<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Service\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\DataObjectFactory;
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
        QuoteRepository $quoteRepository
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
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function process($request)
    {
        $cartHash = $request->getParam('id');
        $this->logging->addDebug(__METHOD__ . '|1|');
        if ($cartHash) {
            $cartId = $this->maskedQuoteIdToQuoteId->execute($cartHash);
            $cart = $this->cartRepository->get($cartId);
        } else {
            $cart = $this->checkoutSession->getQuote();
        }

        $this->logging->addDebug(__METHOD__ . '|2|');

        $product = $request->getParam('product');
        $cart->removeAllItems();

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
                'super_attribute' => $product['selected_options'],
                'qty' => $product['qty'],
            ]]
        );

        $this->logging->addDebug(__METHOD__ . '|6|' . var_export($buyRequest->getData()));

        $cart->addProduct($productToBeAdded, $buyRequest);
        $this->logging->addDebug(__METHOD__ . '|7|');
        $this->cartRepository->save($cart);
        $this->logging->addDebug(__METHOD__ . '|8|');
        $wallet = $request->getParam('wallet');
        $shippingAddressData = $this->applepayModel->processAddressFromWallet($wallet, 'shipping');
        $this->logging->addDebug(__METHOD__ . '|9|');

        /**
         * @var $shippingAddress \Magento\Quote\Model\Quote\Address
         */
        $shippingAddress = $this->quoteAddressFactory->create();
        $shippingAddress->addData($shippingAddressData);

        $errors = $shippingAddress->validate();
//        if (is_array($errors)) {
//            return ['success' => 'false', 'error' => $errors];
//        }

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
                'price_incl_tax' => round($shippingMethod['amount']['value'], 2),
                'method_code' => $shippingMethod['carrier_code'] . '_' .  $shippingMethod['method_code'],
                'method_title' => $shippingMethod['method_title'],
            ];
        }
        $cart->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
        $cart->setTotalsCollectedFlag(false);
        $cart->collectTotals();
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
}
