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

namespace Buckaroo\Magento2\Exception;

use Magento\Framework\Phrase;
use Magento\Payment\Gateway\Http\ClientException;

/**
 * A request reached Buckaroo but its answer was lost, and the transaction it created could not be found.
 *
 * Buckaroo may have processed it (paid, refunded, captured): callers must not treat it as failed.
 */
class UnknownOutcomeException extends ClientException
{
    /**
     * @var string
     */
    private string $action;

    /**
     * @var string
     */
    private string $invoice;

    /**
     * Constructor
     *
     * @param Phrase          $phrase
     * @param string          $action
     * @param string          $invoice
     * @param \Exception|null $cause
     */
    public function __construct(Phrase $phrase, string $action, string $invoice, ?\Exception $cause = null)
    {
        parent::__construct($phrase, $cause);
        $this->action = $action;
        $this->invoice = $invoice;
    }

    /**
     * Get action
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get invoice
     *
     * @return string
     */
    public function getInvoice(): string
    {
        return $this->invoice;
    }
}
