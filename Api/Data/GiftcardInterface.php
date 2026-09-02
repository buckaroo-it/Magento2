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

namespace Buckaroo\Magento2\Api\Data;

interface GiftcardInterface
{
    /**
     * Set Service Code
     *
     * @param string $servicecode
     *
     * @return $this
     */
    public function setServicecode($servicecode);

    /**
     * Get Service Code
     *
     * @return string
     */
    public function getServicecode();

    /**
     * Set Label
     *
     * @param string $label
     *
     * @return $this
     */
    public function setLabel($label);

    /**
     * Get Label
     *
     * @return string
     */
    public function getLabel();

    /**
     * Get acquirer
     *
     * @return string|null $acquirer
     */
    public function getAcquirer();

    /**
     * Set acquirer
     *
     * @param string|null $acquirer
     *
     * @return $this
     */
    public function setAcquirer(?string $acquirer = null);
}
