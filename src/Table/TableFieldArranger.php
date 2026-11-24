<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Table;

use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;

final class TableFieldArranger
{
    /** @param list<string> $visible */
    public static function arrange(EntityCollection $entities, array $visible): void
    {
        foreach ($entities as $entity) {
            $fields = $entity->isAccessible() ? $entity->getFields() : null;
            if (null === $fields) {
                continue;
            }

            $byProperty = [];
            foreach (iterator_to_array($fields) as $field) {
                $byProperty[$field->getProperty()][] = $field;
                $fields->unset($field);
            }

            foreach ($visible as $property) {
                foreach ($byProperty[$property] ?? [] as $field) {
                    $fields->add($field);
                }
            }
        }
    }
}
