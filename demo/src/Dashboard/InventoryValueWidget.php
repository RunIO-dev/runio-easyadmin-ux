<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Runio\EasyAdminUxBundle\Dashboard\MetricWidget;

/** Only managers see this one, to show permission-aware widgets. */
final class InventoryValueWidget extends MetricWidget
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getDefaultPriority(): int
    {
        return 20;
    }

    public function getId(): string
    {
        return 'inventory.value';
    }

    public function getLabel(): string
    {
        return 'Inventory value';
    }

    public function getRequiredPermission(): string
    {
        return 'ROLE_MANAGER';
    }

    public function getValue(): string
    {
        $cents = (int) $this->em->createQuery('SELECT SUM(p.priceInCents * p.stock) FROM '.Product::class.' p')->getSingleScalarResult();

        return '$'.number_format($cents / 100, 2);
    }

    public function getHint(): string
    {
        return 'Price × stock, managers only';
    }
}
