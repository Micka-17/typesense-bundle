<?php

namespace Micka17\TypesenseBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Micka17\TypesenseBundle\DependencyInjection\TypesenseExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class TypesenseBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new TypesenseExtension();
        }
        return $this->extension;
    }
}