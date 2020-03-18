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

class GetShippingMethods extends Common
{
    protected $cart;

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
        \Magento\Checkout\Model\Cart $cart
    ) {
        parent::__construct($context, $resultPageFactory, $inlineParser, $resultJsonFactory, $logger);

        $this->cart = $cart;
    }

    /**
     * @return Page
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();

        $errorMessage = false;
        $data = [];
        $shippingMethodsResult = [];
        if ($isPost) {
            if (
                ($wallet = $this->getRequest()->getParam('wallet'))
            ) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                $checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
                $quoteRepository = $objectManager->get('Magento\Quote\Model\QuoteRepository');

                $quote = $checkoutSession->getQuote();

                ////shipping
                $address = $quote->getShippingAddress();
                $shippingAddress = $this->processAddressFromWallet($wallet, 'shipping');

                $address->addData($shippingAddress);
                $quote->setShippingAddress($address);

                $quote->getPayment()->setMethod(\Buckaroo\Magento2\Model\Method\Applepay::PAYMENT_METHOD_CODE);
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quoteRepository->save($quote);

                $shippingMethodManagement = $objectManager->get('Magento\Quote\Model\ShippingMethodManagement');
                $shippingMethods = $shippingMethodManagement->estimateByExtendedAddress($quote->getId(), $address);

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
