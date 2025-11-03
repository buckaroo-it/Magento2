<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Block\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Sales\Model\Order\Config;

class Success extends \Magento\Checkout\Block\Onepage\Success
{
    /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @param TemplateContext $context
     * @param Session         $checkoutSession
     * @param Config          $orderConfig
     * @param HttpContext     $httpContext
     * @param CurrentCustomer $currentCustomer
     * @param array           $data
     */
    public function __construct(
        TemplateContext $context,
        Session $checkoutSession,
        Config $orderConfig,
        HttpContext $httpContext,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $checkoutSession,
            $orderConfig,
            $httpContext,
            $data
        );
        $this->currentCustomer = $currentCustomer;
    }
}
