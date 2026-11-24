<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Controller;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Runio\EasyAdminUxBundle\Http\SafeReturnPath;
use Runio\EasyAdminUxBundle\Table\InvalidTableStateException;
use Runio\EasyAdminUxBundle\Table\SavedView;
use Runio\EasyAdminUxBundle\Table\TableLayout;
use Runio\EasyAdminUxBundle\Table\TablePreferenceRepository;
use Runio\EasyAdminUxBundle\Table\TablePreferences;
use Runio\EasyAdminUxBundle\Table\TableQuery;
use Runio\EasyAdminUxBundle\Table\TooManySavedViewsException;
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

final class TablePreferenceController
{
    public function __construct(
        private readonly TablePreferenceRepository $repository,
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

        $crud = $request->request->getString('crud');
        $token = new CsrfToken(TablePreferenceRepository::csrfTokenId($crud), $request->request->getString('_token'));
        if ('' === $crud || !$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $adminPath = strstr($request->getBaseUrl().$request->getPathInfo(), '/_ux/', true) ?: '/';
        $returnUrl = SafeReturnPath::resolve($request->request->getString('_return'), $adminPath);
        $preferences = $this->repository->load($user->getUserIdentifier(), $crud);
        $activeViewId = $preferences->view($request->request->getString('view'))?->id;
        $layout = $preferences->layoutFor($activeViewId);

        try {
            [$updated, $redirect] = match ($request->request->getString('action')) {
                'columns' => [$preferences->withLayout($this->columnsLayout($request, $layout), $activeViewId), $returnUrl],
                'density' => [$preferences->withLayout($layout->withDensity($request->request->getString('density')), $activeViewId), $returnUrl],
                'reset' => [$preferences->withLayout(new TableLayout(), $activeViewId), $returnUrl],
                'view_save' => $this->saveView($request, $preferences, $layout, $returnUrl),
                'view_delete' => $this->deleteView($request, $preferences, $activeViewId, $returnUrl),
                default => throw new BadRequestHttpException('Unknown table action.'),
            };

            $this->repository->save($user->getUserIdentifier(), $crud, $updated);
        } catch (TooManySavedViewsException|InvalidTableStateException $e) {
            $this->warn($request, $e->getMessage());
            $redirect = $returnUrl;
        } catch (TableNotFoundException) {
            $this->warn($request, 'Your table settings could not be saved because the database migration has not been run.');
            $redirect = $returnUrl;
        }

        return new RedirectResponse($redirect, Response::HTTP_SEE_OTHER);
    }

    private function columnsLayout(Request $request, TableLayout $current): TableLayout
    {
        $order = $request->request->all('order');
        $visible = $request->request->all('visible');

        return TableLayout::create($order, array_values(array_diff($order, $visible)), $current->density);
    }

    /** @return array{TablePreferences, string} */
    private function saveView(Request $request, TablePreferences $preferences, TableLayout $layout, string $returnUrl): array
    {
        $name = mb_substr(trim(mb_scrub($request->request->getString('name'), 'UTF-8')), 0, SavedView::MAX_NAME_LENGTH);
        if ('' === $name) {
            throw new BadRequestHttpException('A view name is required.');
        }

        $query = TableQuery::fromQueryString($request->request->getString('query'));
        $updated = $preferences->withView(new SavedView(bin2hex(random_bytes(6)), $name, $query, $layout));

        $saved = null;
        foreach ($updated->views as $view) {
            if (mb_strtolower($view->name) === mb_strtolower($name)) {
                $saved = $view;
            }
        }

        return [$updated, TableQuery::url($this->pathOf($returnUrl), $query, $saved?->id)];
    }

    /** @return array{TablePreferences, string} */
    private function deleteView(Request $request, TablePreferences $preferences, ?string $activeViewId, string $returnUrl): array
    {
        $target = $request->request->getString('target');
        $updated = $preferences->withoutView($target);

        if ($target !== $activeViewId) {
            return [$updated, $returnUrl];
        }

        // The view is already being removed, so an unusable query must not block that.
        try {
            $query = TableQuery::fromQueryString((string) parse_url($returnUrl, \PHP_URL_QUERY));
        } catch (InvalidTableStateException) {
            $query = [];
        }

        return [$updated, TableQuery::url($this->pathOf($returnUrl), $query, null)];
    }

    private function pathOf(string $url): string
    {
        return (string) (parse_url($url, \PHP_URL_PATH) ?: '/');
    }

    private function warn(Request $request, string $message): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', $message);
        }
    }
}
