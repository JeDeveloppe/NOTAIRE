<?php

namespace App\Controller\Admin;

use App\Entity\Act;
use App\Entity\City;
use App\Entity\FiscalAbatementRule;
use App\Entity\User;
use App\Entity\Person;
use App\Entity\TypeAct;
use App\Entity\Hypothesis;
use App\Entity\NotaryCoverage;
use App\Entity\NotaryOffice;
use App\Entity\TaxCatalog;
use App\Entity\SimulationResult;
use App\Entity\SubdivisionIndicator;
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
        yield MenuItem::linkToCrud('Les actes', 'fas fa-list', Act::class);
        yield MenuItem::linkToCrud('Types d\'actes', 'fas fa-tags', TypeAct::class);
        yield MenuItem::linkToCrud('Catalogue des abattements et taux', 'fas fa-percentage', TaxCatalog::class);
        yield MenuItem::linkToCrud('Hypothèses de calcul', 'fas fa-calculator', Hypothesis::class);
        yield MenuItem::linkToCrud('Les utilisateurs', 'fas fa-users', User::class);
        yield MenuItem::linkToCrud('Les personnes', 'fas fa-user', Person::class);
        yield MenuItem::linkToCrud('Résultats des simulations', 'fas fa-chart-line', SimulationResult::class);
        yield MenuItem::linkToCrud('Règles d\'abattement fiscal', 'fas fa-rule', FiscalAbatementRule::class);

        yield MenuItem::section('Paramètres géographiques');
        yield MenuItem::linkToCrud('Les villes', 'fas fa-city', City::class);
        yield MenuItem::linkToCrud('Les indicateurs de subdivision', 'fas fa-map', SubdivisionIndicator::class);

        yield MenuItem::section('Notaires');
        yield MenuItem::linkToCrud('Les études notariales', 'fas fa-building', NotaryOffice::class);
        yield MenuItem::linkToCrud('Les couvertures notariales', 'fas fa-map-marker-alt', NotaryCoverage::class);

    }
}
