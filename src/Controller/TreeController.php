<?php

namespace App\Controller;

use App\Service\TreeFormatterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tree')]
#[IsGranted('ROLE_USER')]
class TreeController extends AbstractController
{
    /**
     * Endpoint pour fournir les données JSON hiérarchiques (utilisé par D3.js ou OrgChart.js).
     * Maintenu pour la rétrocompatibilité ou si le JSON est exposé à une API.
     */
    #[Route('/data', name: 'app_tree_data', methods: ['GET'])]
    public function treeData(TreeFormatterService $treeFormatterService): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Le service retourne la structure de données (hiérarchique ou plate)
        $treeData = $treeFormatterService->getTreeDataForUser($user);

        return $this->json($treeData);
    }

    #[Route('/', name: 'app_tree_index', methods: ['GET'])]
    public function index(TreeFormatterService $treeFormatterService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Le service retourne la structure de données hiérarchique
        $treeData = $treeFormatterService->getTreeDataForUser($user);
        
        // La vue Twig va itérer sur ces racines et appeler le template récursif.
        return $this->render('tree/index.html.twig', [
            'root_nodes' => $treeData,
        ]);
    }
}