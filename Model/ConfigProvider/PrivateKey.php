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

namespace Buckaroo\Magento2\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use Buckaroo\Magento2\Api\CertificateRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class PrivateKey implements ConfigProviderInterface
{
    /**
     * Xpath to the 'certificate_upload' setting.
     */
    public const XPATH_CERTIFICATE_ID = 'buckaroo_magento2/account/certificate_file';

    /**
     * @var CertificateRepositoryInterface
     */
    protected CertificateRepositoryInterface $certificateRepository;

    /** @var Account */
    protected Account $account;

    /**
     * @param CertificateRepositoryInterface $certificateRepository
     * @param Account $account
     *
     * @throws \LogicException
     */
    public function __construct(
        CertificateRepositoryInterface $certificateRepository,
        Account $account
    ) {
        $this->certificateRepository = $certificateRepository;
        $this->account = $account;
    }

    /**
     * @inheritdoc
     *
     * @throws NoSuchEntityException|\LogicException
     */
    public function getConfig($store = null): array
    {
        return [
            'private_key' => $this->getPrivateKey($store),
        ];
    }

    /**
     * Return private key from certificate
     *
     * @param int|null|string $store
     * @return string
     * @throws NoSuchEntityException|\LogicException
     */
    public function getPrivateKey($store = null): string
    {
        $certificateId = $this->account->getCertificateFile($store);

        if (!$certificateId) {
            throw new \LogicException('No Buckaroo certificate configured.');
        }

        $certificate = $this->certificateRepository->getById($certificateId);

        return $certificate->getCertificate();
    }
}
