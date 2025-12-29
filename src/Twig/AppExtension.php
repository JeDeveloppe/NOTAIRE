<?php

namespace App\Twig;

use App\Repository\SimulationRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private SimulationRepository $simulationRepository
    ) {}

    public function getGlobals(): array
    {
        // On récupère le vrai compte en BDD
        try {
            $realCount = $this->simulationRepository->countTotalForPublic();
        } catch (\Exception $e) {
            // Sécurité au cas où la table n'existe pas encore (pendant les migrations)
            $realCount = 0;
        }

        // On définit un chiffre de base pour la crédibilité au lancement
        $marketingOffset = 150; 

        return [
            'total_simulations' => $realCount + $marketingOffset,
        ];
    }
}