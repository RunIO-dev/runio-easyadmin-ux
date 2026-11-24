<?php

declare(strict_types=1);

use Runio\EasyAdminUxBundle\Controller\DashboardPreferenceController;
use Runio\EasyAdminUxBundle\Controller\LayoutPreferenceController;
use Runio\EasyAdminUxBundle\Controller\TablePreferenceController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('runio_ux_layout_save', '/_ux/layout')
        ->controller(LayoutPreferenceController::class)
        ->methods(['POST']);

    $routes->add('runio_ux_table_save', '/_ux/table')
        ->controller(TablePreferenceController::class)
        ->methods(['POST']);

    $routes->add('runio_ux_dashboard_save', '/_ux/dashboard')
        ->controller(DashboardPreferenceController::class)
        ->methods(['POST']);
};
