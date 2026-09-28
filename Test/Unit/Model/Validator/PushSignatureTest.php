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

namespace Buckaroo\Magento2\Test\Unit\Model\Validator;

use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Logging\Mail;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\Validator\Push;
use Magento\Framework\Encryption\Encryptor;
use PHPUnit\Framework\TestCase;

/**
 * Push/return signature validation (BTI-1580 backport).
 *
 * The signed string must cover every brq/add/cust parameter regardless of casing, because every consumer reads
 * the parameters case-insensitively. Requests with names that differ only in case are rejected outright.
 */
class PushSignatureTest extends TestCase
{
    private const SECRET = 'DUMMY-TEST-SECRET';

    /**
     * Signature of genuineBody() with SECRET, computed by the algorithm shipped in v1.56.3. A genuine push must keep
     * producing exactly this value.
     */
    private const GOLDEN_SIGNATURE = '3e812cda426e12d403f472135d8f8f254116af43';

    /**
     * @var Push
     */
    private $validator;

    protected function setUp(): void
    {
        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->method('decrypt')->willReturn(self::SECRET);

        $this->validator = new Push(
            $this->createMock(Data::class),
            $this->createMock(Account::class),
            $this->createLogMock(),
            $encryptor
        );
    }

    public function testGenuineSignatureIsUnchangedFromPreviousAlgorithm()
    {
        $this->assertSame(self::GOLDEN_SIGNATURE, $this->validator->calculateSignature($this->genuineBody()));
    }

    public function testGenuinePushValidates()
    {
        $body = $this->signed($this->genuineBody());

        $this->assertTrue($this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER)));
    }

    public function testCaseDuplicateOfSignedParameterIsRejected()
    {
        $body = $this->signed($this->genuineBody(['brq_statuscode' => '890']));
        $body['Brq_statuscode'] = '190';
        $lowerCase = array_change_key_case($body, CASE_LOWER);

        // The reader would act on the forged value...
        $this->assertSame('190', $lowerCase['brq_statuscode']);
        // ...so the request must not validate.
        $this->assertFalse($this->validator->validateSignature($body, $lowerCase));
    }

    public function testCaseDuplicateOfUnsignedParameterIsRejected()
    {
        $body = $this->signed($this->genuineBody());
        $body['form_key'] = 'abc';
        $body['FORM_KEY'] = 'def';

        $this->assertFalse(
            $this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER))
        );
    }

    public function testMixedCasePrefixIsPartOfTheSignedString()
    {
        $body = $this->genuineBody();
        $withMixedCase = $body + ['Brq_extra' => 'value'];

        $this->assertNotSame(
            $this->validator->calculateSignature($body),
            $this->validator->calculateSignature($withMixedCase)
        );
    }

    public function testAddingAnUnsignedMixedCaseParameterBreaksTheSignature()
    {
        $body = $this->signed($this->genuineBody());
        $body['Add_idin_cid'] = '42';

        $this->assertFalse(
            $this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER))
        );
    }

    public function testSignatureParameterIsExcludedInAnyCasing()
    {
        $body = $this->genuineBody();

        $this->assertSame(
            $this->validator->calculateSignature($body),
            $this->validator->calculateSignature($body + ['BRQ_Signature' => 'ignored'])
        );
    }

    public function testMissingSignatureIsRejected()
    {
        $body = $this->genuineBody();

        $this->assertFalse($this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER)));
    }

    public function testEmptySignatureIsRejected()
    {
        $body = $this->genuineBody() + ['brq_signature' => '  '];

        $this->assertFalse($this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER)));
    }

    public function testNonStringSignatureIsRejected()
    {
        $body = $this->genuineBody() + ['brq_signature' => ['x']];

        $this->assertFalse($this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER)));
    }

    public function testWrongSignatureIsRejected()
    {
        $body = $this->genuineBody() + ['brq_signature' => sha1('forged')];

        $this->assertFalse($this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER)));
    }

    public function testSurroundingWhitespaceInSignatureIsIgnored()
    {
        $body = $this->genuineBody();
        $body['brq_signature'] = ' ' . self::GOLDEN_SIGNATURE . "\n";

        $this->assertTrue($this->validator->validateSignature($body, array_change_key_case($body, CASE_LOWER)));
    }

    public function testEmptyRequestIsRejected()
    {
        $this->assertFalse($this->validator->validateSignature([], []));
    }

    /**
     * Key shapes taken from real pushes: lower-case brq_, camel-cased service fields, upper-case ADD_ and CUST_.
     *
     * @param array $overrides
     *
     * @return array
     */
    private function genuineBody(array $overrides = [])
    {
        return array_merge([
            'brq_amount'                       => '26.62',
            'brq_currency'                     => 'EUR',
            'brq_invoicenumber'                => '000000123',
            'brq_mutationtype'                 => 'Collecting',
            'brq_payment'                      => 'D8B8B1B2A8D34E7F9C6B1C2D3E4F5A6B',
            'brq_payment_method'               => 'ideal',
            'brq_SERVICE_ideal_consumerIssuer' => 'ING',
            'brq_SERVICE_ideal_consumerName'   => 'J. de Tester',
            'brq_statuscode'                   => '190',
            'brq_statuscode_detail'            => 'S001',
            'brq_statusmessage'                => 'Transaction successfully processed',
            'brq_test'                         => 'true',
            'brq_timestamp'                    => '2026-09-28 10:32:17',
            'brq_transactions'                 => 'E1AFD88823354282905F03648085F5E9',
            'brq_websitekey'                   => 'ABCDEFGHIJ',
            'ADD_initiated_by_magento'         => '1',
            'ADD_service_action_from_magento'  => 'pay',
            'CUST_customerbillingfirstname'    => 'Jan',
        ], $overrides);
    }

    /**
     * @param array $body
     *
     * @return array
     */
    private function signed(array $body)
    {
        $body['brq_signature'] = $this->validator->calculateSignature($body);

        return $body;
    }

    /**
     * Log::__destruct() mails the debug buffer, so the double needs a Mail instance.
     *
     * @return Log
     */
    private function createLogMock()
    {
        $log = $this->createMock(Log::class);
        $mail = new \ReflectionProperty(Log::class, 'mail');
        if (PHP_VERSION_ID < 80100) {
            $mail->setAccessible(true);
        }
        $mail->setValue($log, $this->createMock(Mail::class));

        return $log;
    }
}
