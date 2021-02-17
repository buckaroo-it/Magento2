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
use Magento\Customer\Model\Session as CustomerSession;

class Add extends Common
{
    protected $formKey;
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
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Catalog\Model\Product $product,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        CustomerSession $customerSession = null
    ) {
        parent::__construct($context, $resultPageFactory, $inlineParser, $resultJsonFactory, $logger, $cart, $totalsCollector, $converter, $customerSession);

        $this->formKey = $formKey;
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
            if (($product = $this->getRequest()->getParam('product'))
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
                $params = [
                    'form_key' => $this->formKey->getFormKey(),
                    'product' => $product['id'],
                    'qty'   => $product['qty']
                ];
                if (!empty($product['selected_options'])) {
                    $params['super_attribute'] = $product['selected_options'];
                }

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                $checkoutSession = $objectManager->get('Magento\Checkout\Model\Session');
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

                $data = $this->getShippingMethods($quote, $objectManager);
            }
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
