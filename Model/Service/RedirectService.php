<?php

namespace Buckaroo\Magento2\Model\Service;

use Magento\Framework\App\ResponseInterface;

class RedirectService
{
    /**
     * Create redirect response
     *
     * @return ResponseInterface
     */
    protected function redirectToCheckout()
    {
        $this->logger->addDebug('start redirectToCheckout');
        if (!$this->customerSession->isLoggedIn()) {
            $this->logger->addDebug('not isLoggedIn');
            if ($this->order->getCustomerId() > 0) {
                $this->logger->addDebug('getCustomerId > 0');
                try {
                    $customer = $this->customerRepository->getById($this->order->getCustomerId());
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);

                    if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                        $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                        $this->logger->addDebug(__METHOD__ . '|setLastRealOrderId|');
                        $this->checkoutSession->restoreQuote();
                        $this->logger->addDebug(__METHOD__ . '|restoreQuote|');
                    } elseif ($this->redirectRequest->hasPostData('primary_service', 'IDIN')) {
                        $this->checkoutSession->restoreQuote();
                    }
                } catch (\Exception $e) {
                    $this->logger->addError('Could not load customer');
                }
            }
        }
        $this->logger->addDebug('ready for redirect');
        return $this->handleProcessedResponse('checkout', ['_query' => ['bk_e' => 1]]);
    }

    /**
     * Redirect to Failure url, which means we've got a problem
     *
     * @return ResponseInterface
     */
    protected function redirectFailure()
    {
        $store = $this->order->getStore();
        $this->logger->addDebug('start redirectFailure');
        if ($this->accountConfig->getFailureRedirectToCheckout($store)) {
            $this->logger->addDebug('getFailureRedirectToCheckout');
            if (!$this->customerSession->isLoggedIn() && ($this->order->getCustomerId() > 0)) {
                $this->logger->addDebug('not isLoggedIn');
                $this->logger->addDebug('getCustomerId > 0');
                try {
                    $customer = $this->customerRepository->getById($this->order->getCustomerId());
                    $this->customerSession->setCustomerDataAsLoggedIn($customer);

                    if (!$this->checkoutSession->getLastRealOrderId() && $this->order->getIncrementId()) {
                        $this->checkoutSession->setLastRealOrderId($this->order->getIncrementId());
                        $this->logger->addDebug(__METHOD__ . '|setLastRealOrderId|');
                        if (!$this->getSkipHandleFailedRecreate()) {
                            $this->checkoutSession->restoreQuote();
                            $this->logger->addDebug(__METHOD__ . '|restoreQuote|');
                        }
                    }
                    $this->setSkipHandleFailedRecreate();
                } catch (\Exception $e) {
                    $this->logger->addError('Could not load customer');
                }
            }
            $this->logger->addDebug('ready for redirect');
            return $this->handleProcessedResponse('checkout', ['_fragment' => 'payment', '_query' => ['bk_e' => 1]]);
        }

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getFailureRedirect($store);

        return $this->handleProcessedResponse($url);
    }
}