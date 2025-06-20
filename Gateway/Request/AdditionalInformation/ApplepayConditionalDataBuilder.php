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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request\AdditionalInformation;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Applepay;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ApplepayConditionalDataBuilder implements BuilderInterface
{
    /**
     * @var Applepay
     */
    private Applepay $applepayConfig;

    /**
     * @var ApplepayDataBuilder
     */
    private ApplepayDataBuilder $inlineDataBuilder;

    /**
     * @var ApplepayRedirectDataBuilder
     */
    private ApplepayRedirectDataBuilder $redirectDataBuilder;

    /**
     * @param Applepay $applepayConfig
     * @param ApplepayDataBuilder $inlineDataBuilder
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
        if ($integrationMode === '0') {
            // Inline mode - no return URLs needed
            return $this->inlineDataBuilder->build($buildSubject);
        } else {
            // Redirect mode - include return URLs
            return $this->redirectDataBuilder->build($buildSubject);
        }
    }
} 