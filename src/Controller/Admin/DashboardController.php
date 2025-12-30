<?php

namespace App\Controller\Admin;

use App\Entity\City;
use App\Entity\Offer;
use App\Entity\Notary;
use App\Entity\OfferPrice;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('NOTAIRE');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToLogout('Logout', 'fas fa-sign-out-alt');

        yield MenuItem::linkToCrud('Notaires', 'fas fa-users', Notary::class);
        yield MenuItem::linkToCrud('Villes', 'fas fa-city', City::class);

        yield MenuItem::linkToCrud('Offres', 'fas fa-bullhorn', Offer::class);
        yield MenuItem::linkToCrud('Prix des offres', 'fas fa-user-check', OfferPrice::class);
        // yield MenuItem::linkToCrud('The Label', 'fas fa-list', EntityClass::class);
    }
}
