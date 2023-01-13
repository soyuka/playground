<?php

// Should be a real guide

namespace App\ApiResource {

    use ApiPlatform\Metadata\ApiResource;
    use Doctrine\ORM\Mapping as ORM;

    /**
     * Book.
     *
     * @author Antoine Bluchet <soyuka@gmail.com>
     */
    #[ApiResource]
    #[ORM\Entity]
    class Book
    {
        #[ORM\Column(type: 'integer')]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'AUTO')]
        private $id;
        #[ORM\Column]
        public $name;
        #[ORM\Column(unique: true)]
        public $isbn;

        public function getId()
        {
            return $this->id;
        }
    }
}

namespace App\Playground {

    use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
    use App\ApiResource\Book;
    use App\Kernel;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpKernel\KernelInterface;

    function request(): Request
    {
        $body = [
            'name' => 'bookToto',
            'isbn' => 'abcd'
        ];
        return Request::create('/books.jsonld', 'POST',[], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body));
    }

    function setup(Kernel $kernel): void
    {
        $kernel->executeMigrations();
    }
}

namespace DoctrineMigrations {

    use Doctrine\DBAL\Schema\Schema;
    use Doctrine\Migrations\AbstractMigration;

    final class Migration extends AbstractMigration
    {
        public function up(Schema $schema): void
        {
            $this->addSql('CREATE TABLE book (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, isbn VARCHAR(255) NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_CBE5A331CC1CF4E6 ON book (isbn)');
        }
    }
}

namespace App\Fixtures {
    use App\ApiResource\Book;
    use Doctrine\Bundle\FixturesBundle\Fixture;
    use Doctrine\Persistence\ObjectManager;
    use Zenstruck\Foundry\AnonymousFactory;
    use function Zenstruck\Foundry\faker;

    final class BookFixtures extends Fixture
    {
        public function load(ObjectManager $manager): void
        {
            $factory = AnonymousFactory::new(Book::class);
            $factory->many(20)->create(static function (int $i): array {
                return [
                    'name' => faker()->name,
                    'isbn' => faker()->isbn10()
                ];
            });
        }
    }
}