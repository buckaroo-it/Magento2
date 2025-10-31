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

namespace Buckaroo\Magento2\Api\Data;

interface GiftcardInterface
{
    /**
     * Set Service Code
     *
     * @param  string $servicecode
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
     * @param  string $label
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
     * @return string|null $acquirer
     */
    public function getAcquirer();

    /**
     * @param string|null $acquirer
     *
     * @return $this
     */
    public function setAcquirer(?string $acquirer = null);
}
