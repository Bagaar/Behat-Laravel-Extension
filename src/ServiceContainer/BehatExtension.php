<?php

namespace Laracasts\Behat\ServiceContainer;

use Behat\Testwork\ServiceContainer\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\DependencyInjection\Definition;
use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Laracasts\Behat\Context\Argument\LaravelArgumentResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;

class BehatExtension implements Extension
{

    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return 'laravel';
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        if (null !== $minkExtension = $extensionManager->getExtension('mink')) {
            $minkExtension->registerDriverFactory(new LaravelFactory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->scalarNode('bootstrap_path')
                    ->defaultValue('bootstrap/app.php')
                ->end()
                ->scalarNode('env_path')
                    ->defaultValue('.env.behat');
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $app = $this->loadLaravel($container, $config);

        $this->loadInitializer($container, $app);
        $this->loadLaravelArgumentResolver($container, $app);
    }

    /**
     * Boot up Laravel.
     *
     * @param ContainerBuilder $container
     * @param array            $config
     * @return mixed
     */
    private function loadLaravel(ContainerBuilder $container, array $config)
    {
        $laravel = new LaravelBooter($container->getParameter('paths.base'), $config['env_path']);

        $container->set('laravel.app', $app = $laravel->boot());

        return $app;
    }

    /**
     * Load the initializer.
     *
     * @param ContainerBuilder    $container
     * @param HttpKernelInterface $app
     */
    private function loadInitializer(ContainerBuilder $container, $app)
    {
        $definition = new Definition('Laracasts\Behat\Context\KernelAwareInitializer', [$app]);

        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, ['priority' => 0]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);

        $container->setDefinition('laravel.initializer', $definition);
    }

    /**
     * Load argument resolver
     *
     * @param  ContainerBuilder $container
     * @param  Application $app
     */
    private function loadLaravelArgumentResolver(ContainerBuilder $container, $app)
    {
        $definition = new Definition(LaravelArgumentResolver::class, [
            new Reference('laravel.app')
        ]);
        $definition->addTag(ContextExtension::ARGUMENT_RESOLVER_TAG, ['priority' => 0]);
        $container->setDefinition('laravel.context.argument.service_resolver', $definition);
    }
}
