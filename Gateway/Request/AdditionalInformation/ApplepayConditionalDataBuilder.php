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

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ApplepayConditionalDataBuilder implements BuilderInterface
{
    /**
     * @var Applepay
     */
    private $applepayConfig;

    /**
     * @var ApplepayDataBuilder
     */
    private $inlineDataBuilder;

    /**
     * @var ApplepayRedirectDataBuilder
     */
    private $redirectDataBuilder;

    /**
     * @param Applepay                    $applepayConfig
     * @param ApplepayDataBuilder         $inlineDataBuilder
     * @param ApplepayRedirectDataBuilder $redirectDataBuilder
     */
    public function __construct(
        Applepay $applepayConfig,
        ApplepayDataBuilder $inlineDataBuilder,
        ApplepayRedirectDataBuilder $redirectDataBuilder
    ) {
        $this->applepayConfig = $applepayConfig;
        $this->inlineDataBuilder = $inlineDataBuilder;
        $this->redirectDataBuilder = $redirectDataBuilder;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        // Get the integration mode: 0 = inline, 1 = redirect
        $integrationMode = $this->applepayConfig->getIntegrationMode();

        // Use the appropriate data builder based on integration mode
        if ($integrationMode === '0' || $integrationMode === 0) {
            // Inline mode - no return URLs needed
            return $this->inlineDataBuilder->build($buildSubject);
        } else {
            // Redirect mode - include return URLs
            return $this->redirectDataBuilder->build($buildSubject);
        }
    }
}
