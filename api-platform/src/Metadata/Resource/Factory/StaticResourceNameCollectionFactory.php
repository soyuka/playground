<?php

declare(strict_types=1);

namespace App\Metadata\Resource\Factory;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\MapDecorated;

#[AsDecorator(decorates: 'api_platform.metadata.resource.name_collection_factory')]
final class StaticResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    /**
     * @param class-string[] $classes
     */
    public function __construct(private readonly array $classes, #[MapDecorated] private readonly ?ResourceNameCollectionFactoryInterface $decorated = null)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function create(): ResourceNameCollection
    {
        $classes = $this->classes;
        if ($this->decorated) {
            foreach ($this->decorated->create() as $resourceClass) {
                $classes[] = $resourceClass;
            }
        }

        return new ResourceNameCollection($this->classes);
    }
}
