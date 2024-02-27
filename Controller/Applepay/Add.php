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
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Buckaroo\Magento2\Service\Applepay\Add as AddService;

class Add extends Common
{
    protected $formKey;
    protected $product;
    protected $addService;
    protected $context;

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
        AddService $addService = null,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Catalog\Model\Product $product,
        \Magento\Quote\Model\Quote\TotalsCollector $totalsCollector,
        \Magento\Quote\Model\Cart\ShippingMethodConverter $converter,
        CustomerSession $customerSession = null

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

        $this->formKey = $formKey;
        $this->product = $product;
        $this->addService = $addService;
        $this->context = $context;
    }

    /**
     * @return Page
     */
    public function execute()
    {
        return $this->commonResponse(
            $this->addService->process($this->getRequest()),
            false
        );
    }
}
