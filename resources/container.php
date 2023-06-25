<?php

use Fusio\Adapter\Cli\Action\CliEngine;
use Fusio\Adapter\Cli\Action\CliProcessor;
use Fusio\Engine\Adapter\ServiceBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container) {
    $services = ServiceBuilder::build($container);
    $services->set(CliProcessor::class);
};
