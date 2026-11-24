<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Controller;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Runio\EasyAdminUxBundle\Http\SafeReturnPath;
use Runio\EasyAdminUxBundle\Layout\LayoutResolver;
use Runio\EasyAdminUxBundle\Layout\UnknownLayoutException;
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

final class LayoutPreferenceController
{
    public const CSRF_TOKEN_ID = 'runio_ux_layout';

    public function __construct(
        private readonly LayoutResolver $resolver,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        if (null === $user) {
            // Handled by the firewall, which sends anonymous users to the login page.
            throw new AccessDeniedException('Authentication is required.');
        }

        $token = new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        try {
            $this->resolver->save($user->getUserIdentifier(), $request->request->getString('layout'));
        } catch (UnknownLayoutException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        } catch (TableNotFoundException) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('warning', 'Your layout could not be saved because the database migration has not been run.');
            }
        }

        $adminPath = strstr($request->getBaseUrl().$request->getPathInfo(), '/_ux/', true) ?: '/';

        return new RedirectResponse(
            SafeReturnPath::resolve($request->request->getString('_return'), $adminPath),
            Response::HTTP_SEE_OTHER,
        );
    }
}
