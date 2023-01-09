<?php

namespace App;

use ApiPlatform\Metadata\ApiResource;
use App\Metadata\Resource\Factory\StaticResourceNameCollectionFactory;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use function App\DependencyInjection\configure;
use function App\Playground\request;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(private string $guide, string $environment, bool $debug) {
        parent::__construct($environment, $debug);
    }

    private function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir.'/{packages}/*.{php,yaml}');
        $container->import($configDir.'/{packages}/'.$this->environment.'/*.{php,yaml}');

        $services = $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
        ;

        $classes = get_declared_classes();
        $resources = [];

        foreach ($classes as $class) {
            $refl = new ReflectionClass($class);
            $ns = $refl->getNamespaceName();
            if (0 !== strpos($ns, 'App')) {
                continue;
            }

            $services->set($class);

            if ($refl->getAttributes(ApiResource::class, \ReflectionAttribute::IS_INSTANCEOF)) {
                $resources[] = $class;
            }
        }

        $services->set(StaticResourceNameCollectionFactory::class)->args(['$classes' => $resources]);

        if (function_exists('App\DependencyInjection\configure')) {
            configure($container);
        }
    }

    public function request(?Request $request = null): void
    {
        if (null === $request && function_exists('App\Playground\request')) {
            $request = request();
        }

        $request = $request ?? Request::create('/docs.json');
        $response = $this->handle($request);
        $response->send();
        $this->terminate($request, $response);
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . $this->guide;
    }
}

