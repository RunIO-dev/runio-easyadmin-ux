<?php

declare(strict_types=1);

namespace App\Dashboard;

use Runio\EasyAdminUxBundle\Dashboard\ListItem;
use Runio\EasyAdminUxBundle\Dashboard\ListWidget;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Hidden until a user adds it, to show optional widgets. */
final class ShortcutsWidget extends ListWidget
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function getId(): string
    {
        return 'shortcuts';
    }

    public function getLabel(): string
    {
        return 'Shortcuts';
    }

    public function isShownByDefault(): bool
    {
        return false;
    }

    public function getItems(): iterable
    {
        yield new ListItem('Add a product', $this->urls->generate('admin_product_new'));
        yield new ListItem('Inactive products', $this->urls->generate('admin_product_index', ['filters' => ['active' => ['value' => '0']]]));
        yield new ListItem('Categories', $this->urls->generate('admin_category_index'));
    }
}
