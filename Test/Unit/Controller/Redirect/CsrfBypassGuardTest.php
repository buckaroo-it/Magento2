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

namespace Buckaroo\Magento2\Test\Unit\Controller\Redirect;

use Buckaroo\Magento2\Controller\Redirect\Process;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Recurrence guard for the CSRF-bypass class of vulnerabilities (BTI-1489).
 *
 * Buckaroo\Magento2\Controller\Redirect\Process disables Magento's form-key CSRF
 * (validateForCsrf() => true) for the whole controller; the compensating control is the
 * Buckaroo signature check in Process::execute(). Any subclass that OVERRIDES execute()
 * silently keeps the CSRF bypass while potentially dropping that signature check - which is
 * exactly how the Mrcash and iDIN vulnerabilities happened.
 *
 * This test fails when a NEW subclass overrides execute() without being consciously added to
 * the reviewed allow-list below. Adding a class here is a signal to verify it validates either
 * the Buckaroo signature or the form key before acting.
 */
class CsrfBypassGuardTest extends TestCase
{
    /**
     * Subclasses of Process that override execute() and have been security-reviewed to enforce
     * signature-or-form-key before performing any state change.
     *
     * @var string[]
     */
    private const REVIEWED_EXECUTE_OVERRIDES = [
        \Buckaroo\Magento2\Controller\Mrcash\Process::class,      // POST + form key + own-order check
        \Buckaroo\Magento2\Controller\Redirect\IdinProcess::class, // signature + session-owned transaction
    ];

    public function testEveryProcessSubclassOverridingExecuteHasBeenReviewed(): void
    {
        $offenders = [];

        foreach ($this->processSubclasses() as $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            $declaringClass = $reflection->getMethod('execute')->getDeclaringClass()->getName();
            if ($declaringClass !== $class) {
                // Inherits Process::execute() (the fully signature-validated flow) - safe.
                continue;
            }

            if (!in_array($class, self::REVIEWED_EXECUTE_OVERRIDES, true)) {
                $offenders[] = $class;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These controllers extend Redirect\\Process and override execute(), so they inherit the "
            . "CSRF bypass. Confirm each validates the Buckaroo signature or the form key before "
            . "acting, then add it to REVIEWED_EXECUTE_OVERRIDES: " . implode(', ', $offenders)
        );
    }

    /**
     * Discover all concrete controller classes that extend Redirect\Process.
     *
     * @return string[]
     */
    private function processSubclasses(): array
    {
        $controllerDir = dirname(__DIR__, 4) . '/Controller';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerDir, \FilesystemIterator::SKIP_DOTS)
        );

        $classes = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->classFromFile((string)$file);
            if ($class === null || !class_exists($class)) {
                continue;
            }

            if (is_subclass_of($class, Process::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Derive the fully-qualified class name from a controller file path.
     *
     * @param string $path
     *
     * @return string|null
     */
    private function classFromFile(string $path): ?string
    {
        $marker = '/Controller/';
        $position = strpos($path, $marker);
        if ($position === false) {
            return null;
        }

        $relative = substr($path, $position + strlen($marker));
        $relative = substr($relative, 0, -strlen('.php'));

        return 'Buckaroo\\Magento2\\Controller\\' . str_replace('/', '\\', $relative);
    }
}
