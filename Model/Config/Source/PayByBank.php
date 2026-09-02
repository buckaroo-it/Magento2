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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\PayByBank as ConfigPayByBank;

class PayByBank implements OptionSourceInterface
{
    /**
     * @var ConfigPayByBank
     */
    protected $configProvider;

    /**
     * Use the constructor to get the requested config provider.
     *
     * @param ConfigPayByBank $configProvider
     */
    public function __construct(
        ConfigPayByBank $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    /**
     * Format the array in such a way Magento can read it.
     *
     * @return array
     */
    public function toOptionArray(): array
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
