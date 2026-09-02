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

namespace Buckaroo\Magento2\Model\Service;

interface QuoteBuilderInterface
{
    /**
     * Set form data
     *
     * @param string $formData
     */
    public function setFormData(string $formData);

    /**
     * Build quote from form data and session
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function build();
}
