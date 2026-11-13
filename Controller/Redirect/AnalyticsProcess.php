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

namespace Buckaroo\Magento2\Controller\Redirect;

use Magento\Framework\App\ResponseInterface;

class AnalyticsProcess extends Process
{
    /**
     * Redirect to Success url, which means everything seems to be going fine
     *
     * @return ResponseInterface
     */
    protected function redirectSuccess(): ResponseInterface
    {
        $this->eventManager->dispatch('buckaroo_process_redirect_success_before');

        $store = $this->order->getStore();

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $url = $this->accountConfig->getSuccessRedirect($store);

        // Store the status code in payment additional information for success page plugin
        $statusCode = (int)$this->redirectRequest->getStatusCode();
        $this->payment->setAdditionalInformation('buckaroo_statuscode', $statusCode);
        $this->paymentRepository->save($this->payment);

        $successMessage = __('Your order has been placed successfully.');
        if (method_exists($this, 'addSuccessMessage')) {
            $this->addSuccessMessage($successMessage);
        } else {
            $this->messageManager->addSuccessMessage($successMessage);
        }

        $this->quote->setReservedOrderId(null);
        $this->customerSession->setSkipSecondChance(false);

        $this->redirectSuccessApplePay();

        // Only include analytics-related query parameters, not all Buckaroo response data
        $queryArguments = [];
        if (class_exists(\Buckaroo\Magento2\Service\CookieParamService::class)) {
            $cookieParamService = $this->_objectManager->get(
                \Buckaroo\Magento2\Service\CookieParamService::class
            );

            $queryArguments = $cookieParamService->getQueryArgumentsByCookies($this->getRequest()->getParams());
        }

        if (strpos($url, '?') !== false) {
            $url = substr($url, 0, strpos($url, '?'));
        }

        if (method_exists($this, 'handleProcessedResponse')) {
            return $this->handleProcessedResponse($url, ['_query' => $queryArguments]);
        }
        return $this->_redirect($url, ['_query' => $queryArguments]);
    }
}
