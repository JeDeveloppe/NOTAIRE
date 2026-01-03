<?php
namespace App\Twig\Extension;

use App\Entity\User;
use App\Repository\NotaryRepository;
use App\Service\NotaryService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class NotaryExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private Security $security,
        private NotaryService $notaryService,
        private NotaryRepository $notaryRepository
    ) {}

    public function getGlobals(): array
{
    $user = $this->security->getUser();
    
    // 1. On vérifie si l'utilisateur est connecté ET s'il a le rôle Notaire
    // Cela évite de charger des services pour un utilisateur normal
    if (!$user instanceof User || !$this->security->isGranted('ROLE_NOTARY')) {
        return [
            'hasActiveOffer' => false,
            'notaryStats' => null,
            'groupedSectors' => [],
            'activeSubscription' => null,
            'notary' => null
        ];
    }

    $notary = $user->getNotary();
    
    // Sécurité supplémentaire au cas où le rôle existe mais pas l'entité
    if (!$notary) {
        return [
            'hasActiveOffer' => false,
            'notaryStats' => null,
            'groupedSectors' => [],
            'activeSubscription' => null,
            'notary' => null
        ];
    }

    // Le reste du code ne s'exécute QUE pour un vrai notaire
    return [
        'hasActiveOffer' => ($this->notaryService->getActiveSubscription($notary) !== null),
        'notaryStats' => $this->notaryRepository->getPerformanceStats($notary),
        'groupedSectors' => $this->notaryService->getGroupedZips($notary),
        'activeSubscription' => $this->notaryService->getActiveSubscription($notary),
        'notary' => $notary
    ];
}
}