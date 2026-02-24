<?php declare(strict_types=1);

namespace Byfareska\Cron;

use Byfareska\Cron\Command\CronCommand;
use Byfareska\Cron\Lock\LockManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class CronBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(LockManager::class)
            ->args(['%kernel.project_dir%']);

        $services->set(CronCommand::class)
            ->public()
            ->args([
                tagged_iterator('cron.task'),
                service(LockManager::class),
                service(LoggerInterface::class)->nullOnInvalid(),
            ])
            ->tag('console.command');
    }
}
