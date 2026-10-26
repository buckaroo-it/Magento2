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
declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Command;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\ArticlesHandlerFactory;
use Buckaroo\Magento2\Gateway\Request\Articles\ArticlesHandler\KlarnaKpHandler;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Model\InfoInterface;

/**
 * Klarna KP capture that first gives the reservation unique article numbers when needed.
 *
 * A reservation without them cannot be partially captured when a SKU is on more than one line.
 * UpdateReservation sends the same lines with unique article numbers, once per reservation.
 * If the update fails or its lines are incomplete, it is not used and the capture still runs.
 */
class UpdateReservationBeforeCaptureCommand implements CommandInterface
{
    /**
     * @var CommandInterface
     */
    private CommandInterface $captureCommand;

    /**
     * @var CommandInterface
     */
    private CommandInterface $updateReservationCommand;

    /**
     * @var ArticlesHandlerFactory
     */
    private ArticlesHandlerFactory $articlesHandlerFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param CommandInterface        $captureCommand
     * @param CommandInterface        $updateReservationCommand
     * @param ArticlesHandlerFactory  $articlesHandlerFactory
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        CommandInterface $captureCommand,
        CommandInterface $updateReservationCommand,
        ArticlesHandlerFactory $articlesHandlerFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->captureCommand = $captureCommand;
        $this->updateReservationCommand = $updateReservationCommand;
        $this->articlesHandlerFactory = $articlesHandlerFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $commandSubject)
    {
        $payment = SubjectReader::readPayment($commandSubject)->getPayment();

        $handler = $this->getHandlerForUpdate($payment);
        if ($handler !== null) {
            $this->updateReservation($commandSubject, $payment, $handler);
        }

        return $this->captureCommand->execute($commandSubject);
    }

    /**
     * The KP articles handler when the reservation still shares article numbers between lines.
     *
     * @param InfoInterface $payment
     *
     * @return KlarnaKpHandler|null
     * @throws Exception
     */
    private function getHandlerForUpdate(InfoInterface $payment): ?KlarnaKpHandler
    {
        if ($payment->getAdditionalInformation(KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS)) {
            return null;
        }

        $handler = $this->articlesHandlerFactory->create((string)$payment->getMethod());
        if (!$handler instanceof KlarnaKpHandler) {
            return null;
        }

        $handler->setOrder($payment->getOrder());

        return $handler->hasDuplicateSku() ? $handler : null;
    }

    /**
     * Send UpdateReservation with unique article numbers.
     *
     * The update replaces every line of the reservation, so it is only sent when its lines include
     * each unique article number the captures will use.
     *
     * @param array           $commandSubject
     * @param InfoInterface   $payment
     * @param KlarnaKpHandler $handler
     *
     * @return void
     */
    private function updateReservation(array $commandSubject, InfoInterface $payment, KlarnaKpHandler $handler): void
    {
        $order = $payment->getOrder();
        $orderIncrementId = $order->getIncrementId();

        try {
            $articles = $handler->getOrderArticlesData($order, $payment)['articles'] ?? [];
            $missing = $handler->getMissingUniqueArticleNumbers($articles);
            if (!empty($missing)) {
                $this->logger->addError(sprintf(
                    '[KLARNA_KP] | [%s:%s] - Did not update the reservation of order %s: the lines built for it'
                    . ' do not include %s.',
                    __METHOD__,
                    __LINE__,
                    $orderIncrementId,
                    implode(', ', $missing)
                ));
                return;
            }

            $this->updateReservationCommand->execute($commandSubject);
            $payment->setAdditionalInformation(KlarnaKpHandler::UNIQUE_ARTICLE_NUMBERS, true);

            $this->logger->addDebug(sprintf(
                '[KLARNA_KP] | [%s:%s] - Updated the reservation of order %s with unique article numbers.',
                __METHOD__,
                __LINE__,
                $orderIncrementId
            ));
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[KLARNA_KP] | [%s:%s] - Could not update the reservation of order %s with unique article'
                . ' numbers: %s',
                __METHOD__,
                __LINE__,
                $orderIncrementId,
                $e->getMessage()
            ));
        }
    }
}
