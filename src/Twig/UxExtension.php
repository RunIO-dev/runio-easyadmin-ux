<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use Runio\EasyAdminUxBundle\Controller\LayoutPreferenceController;
use Runio\EasyAdminUxBundle\Dashboard\DashboardPreferenceRepository;
use Runio\EasyAdminUxBundle\Dashboard\DashboardRenderer;
use Runio\EasyAdminUxBundle\Layout\LayoutDefinition;
use Runio\EasyAdminUxBundle\Layout\LayoutRegistry;
use Runio\EasyAdminUxBundle\Layout\LayoutResolver;
use Runio\EasyAdminUxBundle\Table\TableFieldArranger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UxExtension extends AbstractExtension implements ResetInterface
{
    private ?LayoutDefinition $current = null;

    public function __construct(
        private readonly LayoutRegistry $registry,
        private readonly LayoutResolver $resolver,
        private readonly Security $security,
        private readonly DashboardRenderer $dashboardRenderer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('runio_ux_layout', $this->currentLayout(...)),
            new TwigFunction('runio_ux_layouts', $this->registry->all(...)),
            new TwigFunction('runio_ux_layout_csrf_id', static fn (): string => LayoutPreferenceController::CSRF_TOKEN_ID),
            new TwigFunction('runio_ux_dashboard', $this->renderDashboard(...), ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('runio_ux_apply_table', static function (EntityCollection $entities, array $table): bool {
                TableFieldArranger::arrange($entities, $table['visible']);

                return true;
            }),
        ];
    }

    public function currentLayout(): LayoutDefinition
    {
        return $this->current ??= $this->resolver->resolve($this->security->getUser()?->getUserIdentifier());
    }

    /** Renders a customizable widget grid; each name keeps its own per-user layout. */
    public function renderDashboard(Environment $twig, string $name = 'main'): string
    {
        return $twig->render('@RunioEasyAdminUx/dashboard/grid.html.twig', [
            'dashboard' => $name,
            'csrf_id' => DashboardPreferenceRepository::csrfTokenId($name),
            ...$this->dashboardRenderer->prepare($twig, $name, $this->security->getUser()?->getUserIdentifier()),
        ]);
    }

    public function reset(): void
    {
        $this->current = null;
    }
}
