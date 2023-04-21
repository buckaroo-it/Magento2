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

namespace Buckaroo\Magento2\Api\Data\PaypalExpress;

interface OrderDataItemInterface
{
    /**
     * Set name
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name);

    /**
     * Set value
     *
     * @param mixed $value
     * @return void
     */
    public function setValue($value);

    /**
     * Get name
     *
     * @return string
     */
    public function getName();

    /**
     * Get value
     *
     * @return mixed
     */
    public function getValue();
}
