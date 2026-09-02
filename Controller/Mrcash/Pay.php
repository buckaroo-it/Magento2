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
declare(strict_types=1);

namespace Buckaroo\Magento2\Controller\Mrcash;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\View\Result\PageFactory;

class Pay extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var FormKeyValidator
     */
    protected $formKeyValidator;

    /**
     * @param Context          $context
     * @param PageFactory      $resultPageFactory
     * @param FormKeyValidator $formKeyValidator
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        if (!$this->canShowPage()) {
            $this->_forward('noRoute');
            return;
        }
        return $this->resultPageFactory->create();
    }

    /**
     * Validate form key before showing the page
     *
     * @return bool
     */
    protected function canShowPage(): bool
    {
        return $this->formKeyValidator->validate($this->getRequest());
    }
}
