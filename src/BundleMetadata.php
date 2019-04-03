<?php

namespace XTAIN\SymfonyUtils;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class BundleMetadata
{
    /**
     * @var string
     */
    protected $bundleClass;

    /**
     * @var string
     */
    protected $bundlePath;

    public function __construct(
        string $bundleClass = null
    ) {
        if (is_subclass_of($bundleClass, BundleInterface::class)) {
            $this->bundleClass = $bundleClass;
        } elseif (
            is_subclass_of($bundleClass, ExtensionInterface::class) ||
            is_subclass_of($bundleClass, ConfigurationInterface::class)) {
            $this->bundleClass = $this->detectBundleClassFromDependencyInjection($bundleClass);
        } else {
            throw new \InvalidArgumentException('Cannot detect bundle from given class');
        }
    }

    protected function stripLastNamespaceSegment($namespace) : string
    {
        return preg_replace(
            '/\\\\[^\\\\]+$/',
            '',
            $namespace
        );
    }

    protected function detectBundleClassFromDependencyInjection(string $dependencyInjectionClass) : string
    {
        $reflectionClass = new \ReflectionClass($dependencyInjectionClass);
        $namespace = $this->stripLastNamespaceSegment($reflectionClass->getNamespaceName());

        $bundlePath = realpath(dirname($reflectionClass->getFileName()) .
            DIRECTORY_SEPARATOR . '..');

        $dir = new \DirectoryIterator($bundlePath);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isDot() || !preg_match('/(.*)\.php$/', $fileinfo->getFilename(), $matches)) {
                continue;
            }

            $targetClass = $namespace . '\\' . $matches[1];
            if (class_exists($targetClass) &&
                is_subclass_of($targetClass, BundleInterface::class)) {
                return $targetClass;
            }
        }

        throw new \RuntimeException('Could not detect bundle');
    }

    public function getBundleClass() : string
    {
        return $this->bundleClass;
    }

    /**
     * @return string
     * @throws \ReflectionException
     */
    public function getBundlePath() : string
    {
        if (isset($this->bundlePath)) {
            return $this->bundlePath;
        }

        return $this->bundlePath = realpath(dirname((new \ReflectionClass($this->bundleClass))->getFileName()));
    }

    protected function getBundleNamespace() : string
    {
        return $this->stripLastNamespaceSegment($this->bundleClass);
    }

    public function getConfigurationClass()
    {
        return $this->getBundleNamespace().'\\DependencyInjection\\Configuration';
    }

    public function getDefaultConfigurationName() : string
    {
        $className = $this->getBundleClass();
        if ('Bundle' != substr($className, -6)) {
            throw new \BadMethodCallException('This bundle does not follow the naming convention; you must overwrite the getAlias() method.');
        }
        $classBaseName = substr(strrchr($className, '\\'), 1, -6);

        return Container::underscore($classBaseName);
    }
}