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

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class UpdateShippingMethods extends Common
{
    public function execute()
    {
        $isPost = $this->getRequest()->getPostValue();
        $errorMessage = false;
        $data = [];

        if ($isPost) {
            if ($wallet = $this->getRequest()->getParam('wallet')) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
                $checkoutSession = $objectManager->get(\Magento\Checkout\Model\Session::class);
                $quoteRepository = $objectManager->get(\Magento\Quote\Model\QuoteRepository::class);
                $quote = $checkoutSession->getQuote();
                if (!$quote->getIsVirtual()) {
                    ////shipping
                    $quote->getShippingAddress()->setCollectShippingRates(true);
                    $quote->getShippingAddress()->setShippingMethod($wallet['identifier']);

                    $quote->setTotalsCollectedFlag(false);
                    $quote->collectTotals();
                    $totals = $this->gatherTotals($quote->getShippingAddress(), $quote->getTotals());
                    $quoteRepository->save($quote);
                    $data = [
                        'shipping_methods' => [
                            'code' => $wallet['identifier']
                        ],
                        'totals' => $totals
                    ];

                    //resave proper method
                    $quote->getShippingAddress()->setShippingMethod($wallet['identifier']);
                    $quote->getShippingAddress()->save();
                }
            }
        }

        return $this->commonResponse($data, $errorMessage);
    }
}
