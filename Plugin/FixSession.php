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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;

class FixSession
{
    /**
     * @var Header
     */
    protected $header;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * @param Header                  $header
     * @param BuckarooLoggerInterface $logger
     * @param SessionManager          $sessionManager
     */
    public function __construct(
        Header $header,
        BuckarooLoggerInterface $logger,
        SessionManager $sessionManager
    ) {
        $this->header = $header;
        $this->logger = $logger;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Fix the issue when customers get logged out or lose cart content on Magento storefront
     *
     * @param  PhpCookieManager          $subject
     * @param  string                    $name
     * @param  string                    $value
     * @param  PublicCookieMetadata|null $metadata
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSetPublicCookie(
        PhpCookieManager $subject,
        string $name,
        string $value,
        ?PublicCookieMetadata $metadata = null
    ) {
        if (($metadata && method_exists($metadata, 'getSameSite') && ($name == $this->sessionManager->getName()))
            && $metadata->getSameSite() != 'None') {
            $metadata->setSecure(true);
            $metadata->setSameSite('None');
        }
        return [$name, $value, $metadata];
    }
}
