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

namespace Buckaroo\Magento2\Model\RequestPush;

class AbstractPushRequest
{
    /**
     * Check if methods was called with specific numbers of arguments
     *
     * @param array $args
     * @param int $min
     * @param int $max
     * @param string $methodName
     * @return void
     * @throws \Exception
     */
    protected function checkArguments(array $args, int $min, int $max, string $methodName)
    {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new \Exception(
                'Method ' . $methodName
                . ' needs minimaly ' . $min
                . ' and maximaly ' . $max
                . ' arguments. ' . $argc
                . ' arguments given.'
            );
        }
    }

    /**
     * Generate functions for getters and setters
     *
     * @param string $methodName
     * @param array $args
     * @return void
     * @throws \Exception
     */
    public function __call(string $methodName, array $args)
    {
        if (method_exists($this, $methodName)) {
            call_user_func_array([$this, $methodName], $args);
        }
        if (preg_match('~^(set|get)(.*)$~', $methodName, $matches)) {
            $property = lcfirst($matches[2]);
            switch ($matches[1]) {
                case 'set':
                    $this->checkArguments($args, 1, 1, $methodName);
                    return $this->set($property, $args[0]); /** @phpstan-ignore-line */
                case 'get':
                    $this->checkArguments($args, 0, 0, $methodName);
                    return $this->get($property); /** @phpstan-ignore-line */
                default:
                    throw new \Exception('Method ' . $methodName . ' not exists');
            }
        }
    }

    /**
     * Check if in additional information exist a field with name and has the specified value
     *
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function hasAdditionalInformation(string $name, $value): bool
    {
        $fieldValue = $this->getAdditionalInformation($name); /** @phpstan-ignore-line */
        if (is_array($value) &&
            isset($fieldValue) &&
            in_array($fieldValue, $value)
        ) {
            return true;
        }

        if (isset($fieldValue) &&
            $fieldValue == $value
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if parameter has the specified values
     *
     * @param string $name
     * @param string|float|int|array $value
     * @return bool
     */
    public function hasPostData(string $name, $value): bool
    {
        $getter = 'get' . str_replace('_', '', ucwords($name, '_'));
        $fieldValue = $this->$getter();
        if (is_array($value)
            && isset($fieldValue)
            && in_array($fieldValue, $value)
        ) {
            return true;
        }

        if (isset($fieldValue)
            && $fieldValue == $value
        ) {
            return true;
        }

        return false;
    }
}
