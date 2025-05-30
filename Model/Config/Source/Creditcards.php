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
namespace Buckaroo\Magento2\Model\Config\Source;

class Creditcards implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards
     */
    protected $configProvider;

    /**
     * Use the constructor to get the requested config provider.
     *
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards $configProvider
     */
    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcards $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    /**
     * Format the array in such a way Magento can read it.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $output = [];
        foreach ($this->configProvider->getIssuers() as $issuer) {
            $output[] = [
                'value' => $issuer['code'],
                'label' => $issuer['name'],
            ];
        }

        return $output;
    }
}
