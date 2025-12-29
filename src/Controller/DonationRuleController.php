<?php

namespace App\Controller;

use App\Repository\DonationRuleRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/site', name: 'app_site_')]
class DonationRuleController extends AbstractController
{
    #[Route('/regles-fiscales', name: 'donation_rules')]
    public function index(DonationRuleRepository $donationRuleRepository): Response
    {
        // On récupère toutes les règles stockées en base de données
        // Tu peux aussi faire un ->findOneBy(['slug' => 'rappel-fiscal']) si tu as des slugs
        $rules = $donationRuleRepository->findAll();

        return $this->render('site/donation_rule/liste.html.twig', [
            'rules' => $rules,
        ]);
    }
}