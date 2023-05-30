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

namespace Buckaroo\Magento2\Api\Data;

interface CertificateInterface
{
    /**
     * Set Certificate
     *
     * @param string $certificate
     * @return $this
     */
    public function setCertificate(string $certificate): CertificateInterface;

    /**
     * Get Certificate
     *
     * @return string
     */
    public function getCertificate(): string;

    /**
     * Set Certificate Name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): CertificateInterface;

    /**
     * Get Certificate Name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set the date when was created
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): CertificateInterface;

    /**
     * Get the date when was created
     *
     * @return string
     */
    public function getCreatedAt(): string;
}
