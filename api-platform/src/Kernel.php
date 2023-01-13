<?php

namespace App;

use ApiPlatform\Metadata\ApiResource;
use App\Metadata\Resource\Factory\StaticResourceNameCollectionFactory;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Input\ArrayInput;
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

        $container->parameters()->set(
            'database_url',
            sprintf('sqlite:///%s/%s', $this->getDBDir(), 'data.db')
        );

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

    public function getDBDir(): string
    {
        return $this->getProjectDir() . '/var/databases/' . $this->guide;
    }

    public function executeMigrations(string $direction = Direction::UP): void
    {
        $migrationClasses = array_filter(get_declared_classes(), static function (string $class): string {
            return str_starts_with($class, 'DoctrineMigrations');
        });

        if (!$migrationClasses) {
            return;
        }
        $this->boot();
        @mkdir('var/databases/' . $this->guide, recursive: true);

        foreach ($migrationClasses as $migrationClass) {
            if ("Doctrine\Migrations\AbstractMigration" !== (new ReflectionClass($migrationClass))->getParentClass()->getName()) {
                continue;
            }
            $conf = new Configuration();
            $conf->addMigrationClass($migrationClass);
            $conf->setTransactional(true);
            $conf->setCheckDatabasePlatform(true);
            $meta = new TableMetadataStorageConfiguration();
            $meta->setTableName('doctrine_migration_versions');
            $conf->setMetadataStorageConfiguration($meta);

            $confLoader = new ExistingConfiguration($conf);
            /** @var EntityManagerInterface $em */
            $em = $this->getContainer()->get('doctrine.orm.entity_manager');
            $loader = new ExistingEntityManager($em);
            $dependencyFactory = DependencyFactory::fromEntityManager($confLoader, $loader);

            $dependencyFactory->getMetadataStorage()->ensureInitialized();

            $planCalculator = $dependencyFactory->getMigrationPlanCalculator();
            $plan = $planCalculator->getPlanForVersions([new Version($migrationClass)], $direction);
            $migrator = $dependencyFactory->getMigrator();
            $migratorConfigurationFactory = $dependencyFactory->getConsoleInputMigratorConfigurationFactory();
            $migratorConfiguration = $migratorConfigurationFactory->getMigratorConfiguration(new ArrayInput([]));

            $migrator->migrate($plan, $migratorConfiguration);
        }
    }

    public function loadFixtures(): void
    {
        $fixtureClasses = array_filter(get_declared_classes(), static function (string $class): string {
            return str_starts_with($class, 'App\Fixtures');
        });
        if (!$fixtureClasses) {
            return;
        }
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        foreach ($fixtureClasses as $class) {
            if ("Doctrine\Bundle\FixturesBundle\Fixture" !== ((new ReflectionClass($class))->getParentClass()->getName())) {
                continue;
            }
            (new $class())->load($em);
        }
    }
}

