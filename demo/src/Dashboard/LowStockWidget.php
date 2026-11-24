<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Runio\EasyAdminUxBundle\Dashboard\ListItem;
use Runio\EasyAdminUxBundle\Dashboard\ListWidget;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LowStockWidget extends ListWidget
{
    private const THRESHOLD = 10;

    public function __construct(private readonly EntityManagerInterface $em, private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getDefaultPriority(): int
    {
        return 10;
    }

    public function getId(): string
    {
        return 'products.low_stock';
    }

    public function getLabel(): string
    {
        return 'Low stock';
    }

    public function getItems(): iterable
    {
        $products = $this->em->createQuery('SELECT p FROM '.Product::class.' p WHERE p.stock < :threshold ORDER BY p.stock ASC')
            ->setParameter('threshold', self::THRESHOLD)
            ->setMaxResults(5)
            ->getResult();

        foreach ($products as $product) {
            yield new ListItem($product->name, $this->urls->generate('admin_product_edit', ['entityId' => $product->id]), $product->stock.' left');
        }
    }

    public function getEmptyMessage(): ?string
    {
        return 'Every product is well stocked.';
    }

    public function getMoreUrl(): string
    {
        return $this->urls->generate('admin_product_index', ['sort' => ['stock' => 'ASC']]);
    }
}
