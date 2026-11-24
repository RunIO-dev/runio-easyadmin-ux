<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Runio\EasyAdminUxBundle\Dashboard\MetricWidget;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InactiveProductsWidget extends MetricWidget
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getDefaultPriority(): int
    {
        return 30;
    }

    public function getId(): string
    {
        return 'products.inactive';
    }

    public function getLabel(): string
    {
        return 'Inactive products';
    }

    public function getValue(): int
    {
        return $this->em->getRepository(Product::class)->count(['active' => false]);
    }

    public function getHint(): string
    {
        return 'Hidden from customers';
    }

    public function getUrl(): string
    {
        return $this->urls->generate('admin_product_index', ['filters' => ['active' => ['value' => '0']]]);
    }
}
