<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Runio\EasyAdminUxBundle\Dashboard\BarChartWidget;

final class StockByCategoryWidget extends BarChartWidget
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getId(): string
    {
        return 'stock.by_category';
    }

    public function getLabel(): string
    {
        return 'Stock by category';
    }

    public function getBars(): iterable
    {
        $rows = $this->em->createQuery('SELECT c.name AS name, SUM(p.stock) AS total FROM '.Product::class.' p JOIN p.category c GROUP BY c.id, c.name ORDER BY total DESC')->getArrayResult();

        foreach ($rows as $row) {
            yield $row['name'] => (int) $row['total'];
        }
    }
}
