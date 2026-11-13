<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
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
