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

use Buckaroo\Magento2\Model\ConfigProvider\Method\Googlepay;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Psr\Log\LoggerInterface;

class GooglepayConditionalDataBuilder implements BuilderInterface
{
    /**
     * @var Googlepay
     */
    private $googlepayConfig;

    /**
     * @var GooglepayDataBuilder
     */
    private $inlineDataBuilder;

    /**
     * @var GooglepayRedirectDataBuilder
     */
    private $redirectDataBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Googlepay                      $googlepayConfig
     * @param GooglepayDataBuilder           $inlineDataBuilder
     * @param GooglepayRedirectDataBuilder   $redirectDataBuilder
     * @param LoggerInterface                $logger
     */
    public function __construct(
        Googlepay $googlepayConfig,
        GooglepayDataBuilder $inlineDataBuilder,
        GooglepayRedirectDataBuilder $redirectDataBuilder,
        LoggerInterface $logger
    ) {
        $this->googlepayConfig = $googlepayConfig;
        $this->inlineDataBuilder = $inlineDataBuilder;
        $this->redirectDataBuilder = $redirectDataBuilder;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        // Get the integration mode: 0 = inline, 1 = redirect
        $integrationMode = $this->googlepayConfig->getIntegrationMode();

        $this->logger->info('GooglePay Conditional Builder - Integration Mode', [
            'integrationMode' => $integrationMode,
            'integrationModeType' => gettype($integrationMode),
            'isInline' => ($integrationMode === '0' || $integrationMode === 0),
            'isRedirect' => !($integrationMode === '0' || $integrationMode === 0)
        ]);

        // Use the appropriate data builder based on integration mode
        if ($integrationMode === '0' || $integrationMode === 0) {
            // Inline mode - no return URLs needed
            $this->logger->info('GooglePay - Using INLINE mode builder');
            return $this->inlineDataBuilder->build($buildSubject);
        } else {
            // Redirect mode - include return URLs
            $this->logger->info('GooglePay - Using REDIRECT mode builder');
            return $this->redirectDataBuilder->build($buildSubject);
        }
    }
}
