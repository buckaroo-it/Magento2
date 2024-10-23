<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Helper;

use Magento\Framework\Math\Random;
use Magento\Framework\Exception\LocalizedException;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;

/**
 * Custom CSP Nonce Provider for Magento versions without Magento\Csp\Helper\CspNonceProvider
 */
class CustomCspNonceProvider
{
    private const NONCE_LENGTH = 32;

    private string $nonce;

    private Random $random;

    private DynamicCollector $dynamicCollector;

    public function __construct(
        Random $random,
        DynamicCollector $dynamicCollector
    ) {
        $this->random = $random;
        $this->dynamicCollector = $dynamicCollector;
    }

    /**
     * Generate nonce and add it to the CSP header
     *
     * @return string
     * @throws LocalizedException
     */
    public function generateNonce(): string
    {
        if (empty($this->nonce)) {
            $this->nonce = $this->random->getRandomString(
                self::NONCE_LENGTH,
                Random::CHARS_DIGITS . Random::CHARS_LOWERS
            );

            $policy = new FetchPolicy(
                'script-src',
                false,
                [],
                [],
                false,
                false,
                false,
                [$this->nonce],
                []
            );

            $this->dynamicCollector->add($policy);
        }

        return base64_encode($this->nonce);
    }
}
