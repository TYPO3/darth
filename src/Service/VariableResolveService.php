<?php
declare(strict_types = 1);
namespace TYPO3\Darth\Service;

class VariableResolveService
{
    /**
     * @param mixed $source
     * @param string $propertyName
     * @return mixed
     */
    public function resolve($source, string $propertyName)
    {
        if (is_array($source) || $source instanceof \ArrayAccess) {
            if (isset($source[$propertyName])) {
                return $source[$propertyName];
            }
        }
        if (is_object($source)) {
            if (isset($source->$propertyName)) {
                return $source->$propertyName;
            }
            $methodName = 'get' . ucfirst($propertyName);
            if (method_exists($source, $methodName)) {
                return call_user_func([$source, $methodName]);
            }
        }
        return null;
    }

    /**
     * @param mixed $source
     * @param string $propertyPath
     * @param string $delimiter
     * @return mixed
     */
    public function resolveDeep($source, string $propertyPath, string $delimiter = '.')
    {
        return $this->resolveDeepSteps(
            $source,
            $this->resolvePropertySteps(
                $propertyPath,
                $delimiter
            )
        );
    }

    /**
     * @param mixed $source
     * @param array $propertySteps
     * @return array|mixed
     */
    private function resolveDeepSteps($source, array $propertySteps)
    {
        if (empty($propertySteps)) {
            return $source;
        }
        $currentStep = array_shift($propertySteps);
        // resolve current property from source
        $value = $this->resolve($source, $currentStep);
        if (empty($propertySteps)) {
            return $value;
        }
        // continue resolving next steps
        $this->assertResolvable($value);
        return $this->resolveDeepSteps($value, $propertySteps);
    }

    /**
     * @param mixed $source
     * @throws \LogicException
     */
    private function assertResolvable($source)
    {
        if ($source === null || is_scalar($source)) {
            throw new \LogicException(
                'Cannot traverse null or scalar value',
                1519245232
            );
        }
    }

    /**
     * @param string $propertyPath
     * @param string $delimiter
     * @return array
     */
    private function resolvePropertySteps(string $propertyPath, string $delimiter): array
    {
        return array_filter(
            array_map(
                'trim',
                explode($delimiter, $propertyPath)
            )
        );
    }
}
