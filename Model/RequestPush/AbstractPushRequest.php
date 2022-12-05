<?php

namespace Buckaroo\Magento2\Model\RequestPush;

class AbstractPushRequest
{
    /**
     * @throws \Exception
     */
    protected function checkArguments(array $args, $min, $max, $methodName)
    {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new \Exception('Method ' . $methodName . ' needs minimaly ' . $min . ' and maximaly ' . $max . ' arguments. ' . $argc . ' arguments given.');
        }
    }

    /**
     * @throws \Exception
     */
    public function __call($methodName, $args)
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
     * @param $name
     * @param $value
     * @return bool
     */
    public function hasAdditionalInformation($name, $value): bool
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

    public function hasPostData($name, $value): bool
    {
        $getter = 'get' . str_replace('_', '', ucwords($name, '_'));
        $fieldValue = $this->$getter();
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
}
