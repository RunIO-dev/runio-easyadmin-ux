<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Runio\EasyAdminUxBundle\Dashboard\MetricWidget;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductCountWidget extends MetricWidget
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getDefaultPriority(): int
    {
        return 40;
    }

    public function getId(): string
    {
        return 'products.count';
    }

    public function getLabel(): string
    {
        return 'Products';
    }

    public function getValue(): int
    {
        return $this->em->getRepository(Product::class)->count([]);
    }

    public function getHint(): string
    {
        return 'In the catalog';
    }

    public function getUrl(): string
    {
        return $this->urls->generate('admin_product_index');
    }
}
