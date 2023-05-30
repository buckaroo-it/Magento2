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

namespace Buckaroo\Magento2\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\File\ReadFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Filesystem\Io\File;
use Buckaroo\Magento2\Api\CertificateRepositoryInterface;
use Buckaroo\Magento2\Model\CertificateFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Certificate extends Value
{
    /**
     * @var ReadFactory
     */
    protected $readFactory;

    /**
     * @var WriterInterface
     */
    protected $writer;

    /**
     * @var CertificateFactory
     */
    protected $certificateFactory;

    /**
     * @var CertificateRepositoryInterface
     */
    protected $certificateRepository;

    /**
     * @var File
     */
    protected $file;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ReadFactory $readFactory
     * @param WriterInterface $writer
     * @param CertificateFactory $certificateFactory
     * @param CertificateRepositoryInterface $certificateRepository
     * @param File $file
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ReadFactory $readFactory,
        WriterInterface $writer,
        CertificateFactory $certificateFactory,
        CertificateRepositoryInterface $certificateRepository,
        File $file,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);

        $this->readFactory = $readFactory;
        $this->writer = $writer;
        $this->certificateFactory = $certificateFactory;
        $this->certificateRepository = $certificateRepository;
        $this->file = $file;
    }

    /**
     * Save the certificate
     *
     * @return $this
     * @throws \Exception
     */
    public function save()
    {
        //type == application/x-x509-ca-cert
        if (!empty($this->getFieldsetDataValue('certificate_upload')['name'])) {
            $certFile = $this->getFieldsetDataValue('certificate_upload');
            $certLabel = $this->getFieldsetDataValue('certificate_label');

            if (!$this->validExtension($certFile['name'])) {
                throw new LocalizedException(__('Disallowed file type.'));
            }

            if (strlen(trim($certLabel)) <= 0) {
                throw new LocalizedException(__('Enter a name for the certificate.'));
            }

            /**
             * Read the configuration contents
             */
            /**
             * @var \Magento\Framework\Filesystem\File\Read $read
             */
            $read = $this->readFactory->create($certFile['tmp_name'], \Magento\Framework\Filesystem\DriverPool::FILE);

            $certificate = $this->certificateFactory->create();
            $certificate->setCertificate($read->readAll());
            $certificate->setName($certLabel);
            $this->certificateRepository->save($certificate);

            /**
             * Only update the selected certificate when there is a new certificate uploaded, and the user did not
             * change the selected value.
             */
            $oldValue = $this->_config->getValue(
                'buckaroo_magento2/account/certificate_file',
                $this->getScope(),
                $this->getScopeId()
            );
            $newValue = $this->getFieldsetDataValue('certificate_file');

            if ($oldValue == $newValue) {
                /**
                 * Set the current configuration value to this new uploaded certificate.
                 */
                $this->writer->save(
                    'buckaroo_magento2/account/certificate_file',
                    $certificate->getId(),
                    $this->getScope() ? $this->getScope() : 'default',
                    $this->getScopeId()
                );
            }
        }

        return $this;
    }

    /**
     * Check if extension is valid
     *
     * @param String $filename Name of uplpaded file
     * @return bool
     */
    protected function validExtension($filename)
    {
        $allowedExtensions = ['pem'];

        $extensionData = $this->file->getPathInfo($filename, PATHINFO_EXTENSION);
        $extension = $extensionData['extension'];
        return in_array(strtolower($extension), $allowedExtensions);
    }
}
