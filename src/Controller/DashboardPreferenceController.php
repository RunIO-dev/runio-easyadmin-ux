<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Controller;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Runio\EasyAdminUxBundle\Dashboard\DashboardLayout;
use Runio\EasyAdminUxBundle\Dashboard\DashboardPreferenceRepository;
use Runio\EasyAdminUxBundle\Dashboard\WidgetRegistry;
use Runio\EasyAdminUxBundle\Http\SafeReturnPath;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class DashboardPreferenceController
{
    public function __construct(
        private readonly DashboardPreferenceRepository $repository,
        private readonly WidgetRegistry $registry,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException('Authentication is required.');
        }

        $dashboard = $request->request->getString('dashboard');
        $token = new CsrfToken(DashboardPreferenceRepository::csrfTokenId($dashboard), $request->request->getString('_token'));
        if ('' === $dashboard || !$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $adminPath = strstr($request->getBaseUrl().$request->getPathInfo(), '/_ux/', true) ?: '/';
        $returnUrl = SafeReturnPath::resolve($request->request->getString('_return'), $adminPath);

        // Only widgets this user may see can be placed, whatever the form contains.
        $layout = match ($request->request->getString('action')) {
            'save' => DashboardLayout::customize($this->requestedPlacements($request), $this->registry->available(), $request->request->has('known') ? $request->request->all('known') : null),
            'reset' => new DashboardLayout(),
            default => throw new BadRequestHttpException('Unknown dashboard action.'),
        };

        try {
            $this->repository->save($user->getUserIdentifier(), $dashboard, $layout);
        } catch (TableNotFoundException) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('warning', 'Your dashboard could not be saved because the database migration has not been run.');
            }
        }

        return new RedirectResponse($returnUrl, Response::HTTP_SEE_OTHER);
    }

    /** @return list<array{id: mixed, size: mixed}> */
    private function requestedPlacements(Request $request): array
    {
        $sizes = $request->request->all('sizes');

        return array_map(
            static fn (mixed $id): array => ['id' => $id, 'size' => \is_string($id) ? ($sizes[$id] ?? null) : null],
            array_values($request->request->all('widgets')),
        );
    }
}
