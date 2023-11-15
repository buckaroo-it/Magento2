<?php

namespace Buckaroo\Magento2\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;

class AdminInfo extends ConfigurableInfo
{
    /**
     * Get Specific Payment Details set on Success Push to display on Payment Order Information
     *
     * @return array
     * @throws LocalizedException
     */
    public function getSpecificPaymentDetails(): array
    {
        $details = $this->getInfo()->getAdditionalInformation('specific_payment_details');

        if (!$details || !is_array($details)) {
            return [];
        }

        $transformedKeys = array_map([$this, 'getLabel'], array_keys($details));
        $transformedValues = array_map(function ($value) {
            return $this->getValueView((string)$value);
        }, $details);

        return array_combine($transformedKeys, $transformedValues);
    }

    /**
     * Returns label
     *
     * @param string $field
     * @return Phrase
     */
    protected function getLabel($field)
    {
        $words = explode('_', $field);
        $transformedWords = array_map('ucfirst', $words);
        return __(implode(' ', $transformedWords));
    }
}
