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

namespace Buckaroo\Magento2\Model\Adapter;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException as GuzzleTransferException;

/**
 * Tells why a request to Buckaroo got no usable answer.
 *
 * Guzzle reports every curl failure the same way (ConnectException, mostly curl error 28), whether
 * the request never left the server or Buckaroo received it and the answer never came back. Only
 * the curl transfer statistics in the handler context tell the two apart: a request that was sent
 * has a pre-transfer time and uploaded bytes.
 */
class TransportFailureClassifier
{
    /** curl errors that happen before anything is sent: couldn't resolve proxy/host, couldn't connect, TLS */
    private const CONNECT_ERRNOS = [5, 6, 7, 35, 60];

    /**
     * Classify a throwable raised while talking to Buckaroo
     *
     * @param \Throwable $throwable
     *
     * @return TransportFailure
     */
    public function classify(\Throwable $throwable): TransportFailure
    {
        for ($cause = $throwable; $cause !== null; $cause = $cause->getPrevious()) {
            if (!$cause instanceof GuzzleTransferException) {
                continue;
            }

            if ($cause instanceof RequestException && $cause->hasResponse()) {
                // An HTTP answer arrived (4xx/5xx): Buckaroo or a proxy responded
                return new TransportFailure(TransportFailure::NONE);
            }

            $context = method_exists($cause, 'getHandlerContext') ? (array)$cause->getHandlerContext() : [];

            return new TransportFailure($this->classifyContext($context), $this->summarize($context));
        }

        return new TransportFailure(TransportFailure::NONE);
    }

    /**
     * Classify context
     *
     * @param array $context
     *
     * @return string
     */
    private function classifyContext(array $context): string
    {
        $errno = isset($context['errno']) ? (int)$context['errno'] : null;

        if ($errno !== null && in_array($errno, self::CONNECT_ERRNOS, true)) {
            return TransportFailure::NOT_SENT;
        }

        $sent = (float)($context['pretransfer_time'] ?? 0) > 0 || (int)($context['size_upload'] ?? 0) > 0;

        if (!$sent && array_key_exists('pretransfer_time', $context)) {
            return TransportFailure::NOT_SENT;
        }

        // Sent, or no statistics to prove otherwise: Buckaroo may have the transaction
        return TransportFailure::UNKNOWN_OUTCOME;
    }

    /**
     * Summarize
     *
     * @param array $context
     *
     * @return array
     */
    private function summarize(array $context): array
    {
        $keys = ['errno', 'error', 'primary_ip', 'namelookup_time', 'connect_time', 'appconnect_time',
            'pretransfer_time', 'total_time', 'size_upload'];

        return array_intersect_key($context, array_flip($keys));
    }
}
