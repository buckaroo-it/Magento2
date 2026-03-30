<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Block\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Buckaroo\Magento2\Model\Config\Source\TransferPaymentMethodLogo;
use Buckaroo\Magento2\Service\LogoService;

class TransferLogoPreview extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Buckaroo_Magento2::transfer_logo_preview.phtml';

    /**
     * @var AbstractElement|null
     */
    protected $logoElement = null;

    /**
     * @var LogoService
     */
    private $logoService;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param LogoService $logoService
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        LogoService $logoService,
        array $data = []
    ) {
        $this->logoService = $logoService;
        parent::__construct($context, $data);
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $elementId = str_replace('_preview', '', $element->getId());
        $this->logoElement = $element->getForm()->getElement($elementId);
        return $this->_toHtml();
    }

    /**
     * Get current logo option value from the form
     */
    public function getLogoOption(): string
    {
        if ($this->logoElement !== null) {
            $value = $this->logoElement->getDataByKey('value');
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }
        return TransferPaymentMethodLogo::OPTION_GENERIC_BANK_LOGO;
    }

    /**
     * Get the form element id for the logo select (for JS change binding)
     */
    public function getLogoSelectElementId(): string
    {
        if ($this->logoElement !== null) {
            return (string) $this->logoElement->getId();
        }
        return '';
    }

    /**
     * Get URL for Generic Bank Logo
     */
    public function getGenericLogoUrl(): string
    {
        return $this->logoService->getTransferLogo(TransferPaymentMethodLogo::OPTION_GENERIC_BANK_LOGO);
    }

    /**
     * Get URL for SEPA Credit Transfer Logo
     */
    public function getSepaLogoUrl(): string
    {
        return $this->logoService->getTransferLogo(TransferPaymentMethodLogo::OPTION_SEPA_CREDIT_TRANSFER);
    }

    /**
     * Get current preview logo URL based on selected option
     */
    public function getCurrentPreviewLogoUrl(): string
    {
        $option = $this->getLogoOption();
        return $this->logoService->getTransferLogo($option);
    }
}
