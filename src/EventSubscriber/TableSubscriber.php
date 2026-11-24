<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use Runio\EasyAdminUxBundle\Table\SavedView;
use Runio\EasyAdminUxBundle\Table\TableLayout;
use Runio\EasyAdminUxBundle\Table\TablePreferenceRepository;
use Runio\EasyAdminUxBundle\Table\TableQuery;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prepares each user's table state. The columns are applied by the bundle's list template, so
 * an application that replaces that template never loses columns it has no controls to restore.
 */
final class TableSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TablePreferenceRepository $repository,
        private readonly Security $security,
        private readonly bool $inlineEditing = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [AfterCrudActionEvent::class => 'onAfterCrudAction'];
    }

    public function onAfterCrudAction(AfterCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        $user = $this->security->getUser();
        $controller = $context?->getCrud()?->getControllerFqcn();
        $entities = $event->getResponseParameters()->get('entities');

        if (null === $user || null === $controller || Crud::PAGE_INDEX !== $context->getCrud()->getCurrentPage() || !$entities instanceof EntityCollection) {
            return;
        }

        $request = $context->getRequest();
        $preferences = $this->repository->load($user->getUserIdentifier(), $controller);
        $requestedView = $request->query->all()[TableQuery::VIEW_PARAMETER] ?? null;
        $activeView = \is_string($requestedView) ? $preferences->view($requestedView) : null;
        $layout = $preferences->layoutFor($activeView?->id);

        $fields = $this->firstAccessibleFields($entities);
        $properties = array_values(array_unique(array_map(static fn (FieldDto $field): string => $field->getProperty(), $fields)));
        $visible = $layout->visible($properties);

        $path = $request->getBaseUrl().$request->getPathInfo();
        $event->addResponseParameters(['ux_table' => [
            'crud' => $controller,
            'csrf_id' => TablePreferenceRepository::csrfTokenId($controller),
            'columns' => $this->describeColumns($fields, $layout, $visible, $properties),
            'visible' => $visible,
            'editing' => $this->inlineEditing,
            'density' => $layout->density,
            'densities' => TableLayout::DENSITIES,
            'view' => $activeView?->id,
            'all_url' => TableQuery::url($path, TableQuery::context($request->query->all()), null),
            'views' => array_map(static fn (SavedView $view): array => [
                'id' => $view->id,
                'name' => $view->name,
                'url' => TableQuery::url($path, $view->query, $view->id),
                'active' => $view->id === $activeView?->id,
            ], $preferences->views),
        ]]);
    }

    /** @return list<FieldDto> */
    private function firstAccessibleFields(EntityCollection $entities): array
    {
        foreach ($entities as $entity) {
            if ($entity->isAccessible() && null !== $entity->getFields()) {
                return array_values(iterator_to_array($entity->getFields()));
            }
        }

        return [];
    }

    /**
     * @param list<FieldDto> $fields
     * @param list<string>   $visible
     * @param list<string>   $properties
     *
     * @return list<array{property: string, label: mixed, visible: bool}>
     */
    private function describeColumns(array $fields, TableLayout $layout, array $visible, array $properties): array
    {
        $labels = [];
        foreach ($fields as $field) {
            $label = $field->getLabel();
            $labels[$field->getProperty()] ??= \in_array($label, [null, false, ''], true)
                ? ucfirst(trim((string) preg_replace('/(?<!^)[A-Z]|[._]/', ' $0', $field->getProperty())))
                : $label;
        }

        return array_map(static fn (string $property): array => [
            'property' => $property,
            'label' => $labels[$property],
            'visible' => \in_array($property, $visible, true),
        ], $layout->ordered($properties));
    }
}
