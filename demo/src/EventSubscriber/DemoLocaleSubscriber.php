<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Runio\EasyAdminUxBundle\Http\SafeReturnPath;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Remembers the language picked in the demo's user menu for the rest of the session. */
final class DemoLocaleSubscriber implements EventSubscriberInterface
{
    private const LOCALES = ['en', 'ar'];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 100]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $requested = $request->query->all()['_locale'] ?? null;
        if (\is_string($requested) && \in_array($requested, self::LOCALES, true)) {
            $session->set('_demo_locale', $requested);
            $query = $request->query->all();
            unset($query['_locale']);

            // The path comes from the URL, and one like //example.com must not redirect off-site.
            $path = SafeReturnPath::resolve($request->getBaseUrl().$request->getPathInfo(), '/');
            $event->setResponse(new RedirectResponse($path.($query ? '?'.http_build_query($query) : '')));

            return;
        }

        $locale = $session->get('_demo_locale');
        if (\is_string($locale)) {
            $request->setLocale($locale);
            $request->attributes->set('_locale', $locale);
        }
    }
}
