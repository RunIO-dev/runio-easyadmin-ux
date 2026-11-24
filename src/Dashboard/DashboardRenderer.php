<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

use Psr\Log\LoggerInterface;
use Twig\Environment;

final class DashboardRenderer
{
    public function __construct(
        private readonly WidgetRegistry $registry,
        private readonly DashboardPreferenceRepository $repository,
        private readonly bool $debug,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array{
     *     widgets: list<array{id: string, label: string, size: string, html: ?string, failed: bool}>,
     *     hidden: list<array{id: string, label: string, size: string}>,
     *     known: list<string>,
     *     sizes: list<string>,
     * }
     */
    public function prepare(Environment $twig, string $dashboard, ?string $userIdentifier): array
    {
        $available = $this->registry->available();
        $layout = null === $userIdentifier ? new DashboardLayout() : $this->repository->load($userIdentifier, $dashboard);

        $widgets = [];
        foreach ($layout->resolve($available) as $placement) {
            $widget = $available[$placement->id];
            $html = $this->renderWidget($twig, $widget);
            $widgets[] = [
                'id' => $placement->id,
                'label' => $this->labelOf($widget),
                'size' => $placement->size->value,
                'html' => $html,
                'failed' => null === $html,
            ];
        }

        return [
            'widgets' => $widgets,
            'hidden' => array_values(array_map(fn (WidgetInterface $widget): array => [
                'id' => $widget->getId(),
                'label' => $this->labelOf($widget),
                'size' => $widget->getDefaultSize()->value,
            ], $layout->hidden($available))),
            'known' => array_keys($available),
            'sizes' => array_map(static fn (WidgetSize $size): string => $size->value, WidgetSize::cases()),
        ];
    }

    private function labelOf(WidgetInterface $widget): string
    {
        try {
            return $widget->getLabel();
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }
            $this->logger?->error('Dashboard widget "{widget}" failed to return its label.', ['widget' => $widget->getId(), 'exception' => $e]);

            return $widget->getId();
        }
    }

    // One broken widget must not take the whole dashboard down, except while developing.
    private function renderWidget(Environment $twig, WidgetInterface $widget): ?string
    {
        try {
            $view = $widget->render();

            return $twig->render($view->template, $view->parameters);
        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }

            $this->logger?->error('Dashboard widget "{widget}" failed to render.', ['widget' => $widget->getId(), 'exception' => $e]);

            return null;
        }
    }
}
