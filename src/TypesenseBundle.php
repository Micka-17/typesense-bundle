<?php

namespace Micka17\TypesenseBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Micka17\TypesenseBundle\DependencyInjection\TypesenseExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TypesenseBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new TypesenseExtension();
        }
        return $this->extension;
    }

    public function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../config/routes.yaml');
    }
}