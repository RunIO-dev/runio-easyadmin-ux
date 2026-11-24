<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Places this bundle's templates just before EasyAdmin's own in the @EasyAdmin namespace,
 * so application overrides in templates/bundles/EasyAdminBundle keep the highest priority.
 */
final class EasyAdminTemplatePass implements CompilerPassInterface
{
    public function __construct(private readonly string $templatePath)
    {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')) {
            return;
        }

        $loader = $container->getDefinition('twig.loader.native_filesystem');
        $calls = $loader->getMethodCalls();

        $vendorIndex = null;
        foreach ($calls as $index => [$method, $arguments]) {
            if ('addPath' === $method && 'EasyAdmin' === ($arguments[1] ?? null)) {
                $vendorIndex = $index;
            }
        }

        if (null === $vendorIndex) {
            return;
        }

        array_splice($calls, $vendorIndex, 0, [['addPath', [$this->templatePath, 'EasyAdmin']]]);
        $loader->setMethodCalls($calls);
    }
}
