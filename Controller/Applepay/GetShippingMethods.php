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
use Buckaroo\Magento2\Service\Applepay\GetShippingMethods as GetShippingMethodsService;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class GetShippingMethods extends Common
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Translate\Inline\ParserInterface $inlineParser,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Log $logger,        
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        CustomerSession $customerSession = null,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository
        
    ) {
        parent::__construct(
            $context,
            $resultPageFactory,
            $inlineParser,
            $resultJsonFactory,
            $logger,
            $cart,
            $totalsCollector,
            $converter,
            $customerSession
        );
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
    }
    /**
     * @return Page
     */
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();

        $errorMessage = false;
        $data = [];
        if ($isPost) {
            if (($wallet = $this->getRequest()->getParam('wallet'))
            ) {
                $cart_hash = $this->getRequest()->getParam('id');
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                
                if($cart_hash) {
                    $cartId = $this->maskedQuoteIdToQuoteId->execute($cart_hash);
                    $quote = $this->cartRepository->get($cartId);
                } else {
                    
                    $checkoutSession = $objectManager->get(\Magento\Checkout\Model\Session::class);
                    $quote = $checkoutSession->getQuote();
                }
        
                if (!$quote->getIsVirtual() && !$this->setShippingAddress($quote, $wallet)) {
                    return $this->commonResponse(false, true);
                }

                $data = $this->getShippingMethods($quote, $objectManager);
            }
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
