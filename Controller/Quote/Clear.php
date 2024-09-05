<?php

namespace Buckaroo\Magento2\Controller\Quote;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Buckaroo\Magento2\Model\Ideal\QuoteCreate;

class Clear implements HttpPostActionInterface
{
    /**
     * @var QuoteCreate
     */
    protected $quoteCreate;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Clear constructor.
     *
     * @param QuoteCreate $quoteCreate
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        QuoteCreate $quoteCreate,
        JsonFactory $resultJsonFactory
    ) {
        $this->quoteCreate = $quoteCreate;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Execute method for clearing the quote.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $this->quoteCreate->clearQuote();
            return $resultJson->setData(['success' => true]);
        } catch (LocalizedException $e) {
            return $resultJson->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $resultJson->setData(['success' => false, 'message' => __('An error occurred while clearing the quote.')]);
        }
    }
}
