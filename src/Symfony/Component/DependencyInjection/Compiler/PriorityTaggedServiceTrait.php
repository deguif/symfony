<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Trait that allows a generic method to find and sort service by priority option in the tag.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
trait PriorityTaggedServiceTrait
{
    /**
     * Finds all services with the given tag name and order them by their priority.
     *
     * The order of additions must be respected for services having the same priority,
     * and knowing that the \SplPriorityQueue class does not respect the FIFO method,
     * we should not use that class.
     *
     * @see https://bugs.php.net/bug.php?id=53710
     * @see https://bugs.php.net/bug.php?id=60926
     *
     * @param string           $tagName
     * @param ContainerBuilder $container
     *
     * @return Reference[]
     */
    private function findAndSortTaggedServices($tagName, ContainerBuilder $container, string $indexAttribute = null, string $defaultIndexMethod = null)
    {
        $services = array();

        if (null === $indexAttribute && null !== $defaultIndexMethod) {
            throw new InvalidArgumentException('Default index method cannot be used without specifying a tag attribute.');
        }

        foreach ($container->findTaggedServiceIds($tagName, true) as $serviceId => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;

            if (null === $indexAttribute && null === $defaultIndexMethod) {
                $services[$priority][] = new Reference($serviceId);

                continue;
            }

            if (isset($attributes[0][$indexAttribute])) {
                $services[$priority][$attributes[0][$indexAttribute]] = new Reference($serviceId);

                continue;
            }

            if (null === $defaultIndexMethod) {
                throw new LogicException(sprintf('Tag attribute with name "%s" for service "%s" cannot be found.', $indexAttribute, $serviceId));
            }

            if (!$r = $container->getReflectionClass($class = $container->getDefinition($serviceId)->getClass())) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $serviceId));
            }

            if (!$r->hasMethod($defaultIndexMethod)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" has no method "%s".', $class, $serviceId, $defaultIndexMethod));
            }

            if (!($rm = $r->getMethod($defaultIndexMethod))->isStatic()) {
                throw new InvalidArgumentException(sprintf('Method "%s" of class "%s" used for service "%s" must be static.', $defaultIndexMethod, $class, $serviceId));
            }

            if (!$rm->isPublic()) {
                throw new InvalidArgumentException(sprintf('Method "%s" of class "%s" used for service "%s" must be public.', $defaultIndexMethod, $class, $serviceId));
            }

            $key = $rm->invoke(null);

            if (!\is_string($key)) {
                throw new LogicException(sprintf('Return value of method "%s" for class "%s" used for service "%s" must be of type string.', $class, $serviceId, $defaultIndexMethod));
            }

            $services[$priority][$key] = new Reference($serviceId);
        }

        if ($services) {
            krsort($services);
            $services = array_merge(...$services);
        }

        return $services;
    }
}
