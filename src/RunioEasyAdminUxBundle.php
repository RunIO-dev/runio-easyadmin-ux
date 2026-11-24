<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle;

use Runio\EasyAdminUxBundle\Controller\DashboardPreferenceController;
use Runio\EasyAdminUxBundle\Controller\LayoutPreferenceController;
use Runio\EasyAdminUxBundle\Dashboard\DashboardPreferenceRepository;
use Runio\EasyAdminUxBundle\Dashboard\DashboardRenderer;
use Runio\EasyAdminUxBundle\Dashboard\WidgetInterface;
use Runio\EasyAdminUxBundle\Dashboard\WidgetRegistry;
use Runio\EasyAdminUxBundle\Controller\TablePreferenceController;
use Runio\EasyAdminUxBundle\DependencyInjection\EasyAdminTemplatePass;
use Runio\EasyAdminUxBundle\DependencyInjection\OrmMappingConfig;
use Runio\EasyAdminUxBundle\EventSubscriber\TableSubscriber;
use Runio\EasyAdminUxBundle\Layout\LayoutRegistry;
use Runio\EasyAdminUxBundle\Layout\LayoutResolver;
use Runio\EasyAdminUxBundle\Preference\DbalPreferenceStore;
use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;
use Runio\EasyAdminUxBundle\Table\TablePreferenceRepository;
use Runio\EasyAdminUxBundle\Twig\UxExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class RunioEasyAdminUxBundle extends AbstractBundle
{
    public const WIDGET_TAG = 'runio_ux.dashboard_widget';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $builtIn = array_keys(LayoutRegistry::BUILT_IN);

        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info('Name of the Doctrine DBAL connection that stores user preferences.')
                    ->defaultValue('default')
                ->end()
                ->booleanNode('inline_editing')
                    ->info('Adds cell editing and the edit drawer to list pages. EasyAdmin still authorizes and validates every save.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('default_layout')->defaultValue('sidebar')->end()
                ->arrayNode('layouts')
                    ->scalarPrototype()->end()
                    ->defaultValue($builtIn)
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => [] !== array_diff($config['layouts'], $builtIn)
                    || !\in_array($config['default_layout'], $config['layouts'], true))
                ->thenInvalid('"layouts" may only contain '.implode(', ', $builtIn).', and must include "default_layout". Got: %s')
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new EasyAdminTemplatePass($this->getPath().'/templates/easyadmin'));
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine')) {
            return;
        }

        $config = OrmMappingConfig::build($builder->getExtensionConfig('doctrine'), [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => $this->getPath().'/src/Entity',
            'prefix' => 'Runio\EasyAdminUxBundle\Entity',
            'alias' => 'RunioEasyAdminUx',
        ]);

        if (null !== $config) {
            $builder->prependExtensionConfig('doctrine', $config);
        }
    }

    /** @param array{connection: string, inline_editing: bool, default_layout: string, layouts: list<string>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(LayoutRegistry::class)
            ->factory([LayoutRegistry::class, 'fromBuiltIn'])
            ->args([$config['layouts'], $config['default_layout']]);

        $services->set(DbalPreferenceStore::class)
            ->args([service(\sprintf('doctrine.dbal.%s_connection', $config['connection']))]);
        $services->alias(PreferenceStoreInterface::class, DbalPreferenceStore::class);

        $services->set(LayoutResolver::class)
            ->args([service(LayoutRegistry::class), service(PreferenceStoreInterface::class)]);

        $builder->registerForAutoconfiguration(WidgetInterface::class)->addTag(self::WIDGET_TAG);

        $services->set(WidgetRegistry::class)
            ->args([tagged_iterator(self::WIDGET_TAG, defaultPriorityMethod: 'getDefaultPriority'), service('security.authorization_checker')]);

        $services->set(DashboardPreferenceRepository::class)
            ->args([service(PreferenceStoreInterface::class)]);

        $services->set(DashboardRenderer::class)
            ->args([service(WidgetRegistry::class), service(DashboardPreferenceRepository::class), param('kernel.debug'), service('logger')->nullOnInvalid()]);

        $services->set(DashboardPreferenceController::class)
            ->public()
            ->args([service(DashboardPreferenceRepository::class), service(WidgetRegistry::class), service('security.helper'), service('security.csrf.token_manager')])
            ->tag('controller.service_arguments');

        $services->set(UxExtension::class)
            ->args([service(LayoutRegistry::class), service(LayoutResolver::class), service('security.helper'), service(DashboardRenderer::class)])
            ->tag('twig.extension')
            ->tag('kernel.reset', ['method' => 'reset']);

        $services->set(LayoutPreferenceController::class)
            ->public()
            ->args([service(LayoutResolver::class), service('security.helper'), service('security.csrf.token_manager')])
            ->tag('controller.service_arguments');

        $services->set(TablePreferenceRepository::class)
            ->args([service(PreferenceStoreInterface::class)]);

        $services->set(TableSubscriber::class)
            ->args([service(TablePreferenceRepository::class), service('security.helper'), $config['inline_editing']])
            ->tag('kernel.event_subscriber');

        $services->set(TablePreferenceController::class)
            ->public()
            ->args([service(TablePreferenceRepository::class), service('security.helper'), service('security.csrf.token_manager')])
            ->tag('controller.service_arguments');
    }
}
