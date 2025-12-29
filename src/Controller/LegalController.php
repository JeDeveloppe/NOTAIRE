<?php

namespace App\Controller;

use App\Repository\SimulationStatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal', name: 'app_site_')]
final class LegalController extends AbstractController
{
    #[Route('/cgu', name: 'legal_cgu')]
    public function index(): Response
    {
        //TODO remplir les mentions legales
        return $this->render('site/legal/cgu.html.twig');
    }

    #[Route('/cgv-pro', name: 'legal_cgv_pro')]
    public function cgvPro(SimulationStatusRepository $simulationStatusRepository): Response
    {
        return $this->render('site/legal/cgv_pro.html.twig', [
            'available_statuses' => $simulationStatusRepository->findAll(),
        ]);
    }

    #[Route('/rgpd', name: 'legal_rgpd')]
    public function rgpd(): Response
    {
        //TODO supprimer utilisateur apres 1 an d'inactivité => vérifier omment faire pour le suivi des connexions

        return $this->render('site/legal/rgpd.html.twig');
    }
}
