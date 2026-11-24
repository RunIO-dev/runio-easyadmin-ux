<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Runio Demo')->setLocales(['en' => 'English', 'ar' => 'العربية']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Catalog');
        yield MenuItem::linkTo(ProductCrudController::class, 'Products', 'fa fa-box');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Categories', 'fa fa-tags');
        yield MenuItem::subMenu('Shortcuts', 'fa fa-bolt')->setSubItems([
            MenuItem::linkTo(ProductCrudController::class, 'All products', 'fa fa-box'),
            MenuItem::linkTo(CategoryCrudController::class, 'All categories', 'fa fa-tags'),
        ]);
        yield MenuItem::section('Account');
        yield MenuItem::linkToLogout('Log out', 'fa fa-sign-out');
    }
}
