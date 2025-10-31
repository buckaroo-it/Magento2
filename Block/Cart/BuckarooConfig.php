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

namespace Buckaroo\Magento2\Block\Cart;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Json\Encoder as JsonEncoder;

class BuckarooConfig extends Template
{
    /**
     * @var Factory
     */
    protected $configProviderFactory;

    /**
     * @var JsonEncoder
     */
    protected $jsonEncoder;

    /**
     * @param Context     $context
     * @param JsonEncoder $jsonEncoder
     * @param Factory     $configProviderFactory
     * @param array       $data
     */
    public function __construct(
        Context $context,
        JsonEncoder $jsonEncoder,
        Factory $configProviderFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->jsonEncoder = $jsonEncoder;
        $this->configProviderFactory = $configProviderFactory;
    }

    /**
     * Retrieve buckaroo configuration
     *
     * @throws Exception
     * @return string
     */
    public function getBuckarooConfigJson()
    {
        $configProvider = $this->configProviderFactory->get('buckaroo_fee');
        return $this->jsonEncoder->encode($configProvider->getConfig());
    }
}
