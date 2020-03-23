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
namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Add extends Common
{
    protected $formKey;
    protected $cart;
    protected $product;

    /**
     * @param Context     $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Translate\Inline\ParserInterface $inlineParser,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Log $logger,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Catalog\Model\Product $product
    ) {
        parent::__construct($context, $resultPageFactory, $inlineParser, $resultJsonFactory, $logger);

        $this->formKey = $formKey;
        $this->cart = $cart;
        $this->product = $product;
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();

        $this->logger->addDebug(__METHOD__.'|1|');
        $errorMessage = false;
        $data = [];
        $shippingMethodsResult = [];
        if ($isPost) {
            if (
                ($product = $this->getRequest()->getParam('product'))
                &&
                !empty($product['id'])
                &&
                !empty($product['qty'])
                &&
                ($wallet = $this->getRequest()->getParam('wallet'))
            ) {
                $this->logger->addDebug(__METHOD__.'|2|');
                $this->logger->addDebug(var_export($wallet, true));

                ////products
                $params = array(
                    'form_key' => $this->formKey->getFormKey(),
                    'product' => $product['id'],
                    'qty'   => $product['qty']
                );
                if (!empty($product['selected_options'])) {
                    $params['super_attribute'] = $product['selected_options'];
                }

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                $checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
                $quoteRepository = $objectManager->get('Magento\Quote\Model\QuoteRepository');

                $quote = $checkoutSession->getQuote();
                $quote->removeAllItems();

                //Load the product based on productID
                $_product = $this->product->load($product['id']);
                $this->cart->addProduct($_product, $params);
                $this->cart->save();

                $this->logger->addDebug(__METHOD__.'|3|');

                if (!$this->setShippingAddress($quote, $wallet)) {
                    return $this->commonResponse(false, true);
                }

                $quote->getPayment()->setMethod(\Buckaroo\Magento2\Model\Method\Applepay::PAYMENT_METHOD_CODE);
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quoteRepository->save($quote);

                $shippingMethodManagement = $objectManager->get('Magento\Quote\Model\ShippingMethodManagement');
                $shippingMethods = $shippingMethodManagement->estimateByExtendedAddress($quote->getId(), $quote->getShippingAddress());

                if (count($shippingMethods) == 0) {
                    $errorMessage = __(
                        'Apple Pay payment failed, because no shipping methods were found for the selected address. Please select a different shipping address within the pop-up or within your Apple Pay Wallet.'
                    );
                    $this->messageManager->addErrorMessage($errorMessage);
                } else {

                    foreach ($shippingMethods as $index => $shippingMethod) {
                        $shippingMethodsResult[] = [
                            'carrier_title' => $shippingMethod->getCarrierTitle(),
                            'price_incl_tax' => round($shippingMethod->getAmount(), 2),
                            'method_code' => $shippingMethod->getCarrierCode() . '_' .  $shippingMethod->getMethodCode(),
                            'method_title' => $shippingMethod->getMethodTitle(),
                        ];
                    }

                    $quote->getShippingAddress()->setShippingMethod($shippingMethodsResult[0]['method_code']);
                    $quote->setTotalsCollectedFlag(false);
                    $quote->collectTotals();
                    $totals = $this->gatherTotals($quote->getShippingAddress(), $quote->getTotals());
                    $data = [
                        'shipping_methods' => $shippingMethodsResult,
                        'totals' => $totals
                    ];
                    $quoteRepository->save($quote);
                    $this->cart->save();
                }
            }
        }

        return $this->commonResponse($data, $errorMessage);
    }

}
