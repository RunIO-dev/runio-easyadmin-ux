<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed-demo', description: 'Adds sample catalog data when the catalog is empty. Use --reset to start over.')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Delete all catalog data and user preferences first, so IDs start at 1 again.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('reset')) {
            if ('prod' === $this->environment) {
                $output->writeln('<error>--reset deletes all data and is not allowed in the prod environment.</error>');

                return Command::FAILURE;
            }
            if (!$this->entityManager->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                $output->writeln('<error>--reset only supports MySQL and MariaDB, which the demo uses.</error>');

                return Command::FAILURE;
            }
            $this->reset();
            $output->writeln('Deleted catalog data and user preferences.');
        }

        if ($this->entityManager->getRepository(Category::class)->count([]) > 0) {
            $output->writeln('Catalog already has data, nothing to do.');

            return Command::SUCCESS;
        }

        $catalog = [
            'Audio' => ['Wireless Headphones', 'Studio Microphone', 'Bookshelf Speakers', 'Portable Speaker'],
            'Computing' => ['Mechanical Keyboard', 'Ergonomic Mouse', '4K Monitor', 'USB-C Dock', 'Laptop Stand'],
            'Home Office' => ['Desk Lamp', 'Standing Desk', 'Task Chair', 'Cable Organizer'],
        ];

        $number = 1;
        foreach ($catalog as $categoryName => $productNames) {
            $category = new Category();
            $category->name = $categoryName;
            $this->entityManager->persist($category);

            foreach ($productNames as $productName) {
                $product = new Product();
                $product->name = $productName;
                $product->sku = \sprintf('MG-%04d', $number);
                $product->priceInCents = 1999 + $number * 1350;
                $product->stock = ($number * 7) % 40;
                $product->active = 0 !== $number % 5;
                $product->category = $category;
                $this->entityManager->persist($product);
                ++$number;
            }
        }

        $this->entityManager->flush();
        $output->writeln(\sprintf('Added %d products.', $number - 1));

        return Command::SUCCESS;
    }

    /**
     * TRUNCATE resets the auto-increment counters, which the browser tests rely on.
     * It commits immediately, so a failure while seeding leaves the catalog empty; run the command again.
     */
    private function reset(): void
    {
        $connection = $this->entityManager->getConnection();
        $schema = $connection->createSchemaManager();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (['product', 'category', 'runio_ux_preference'] as $table) {
                if ($schema->tablesExist([$table])) {
                    $connection->executeStatement('TRUNCATE TABLE '.$connection->quoteSingleIdentifier($table));
                }
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
        $this->entityManager->clear();
    }
}
