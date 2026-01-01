<?php

namespace App\Service;

use App\Entity\Simulation;
use App\Entity\SimulationStep;
use App\Entity\User;
use App\Repository\SimulationStatusRepository;
use Doctrine\ORM\EntityManagerInterface;

class SimulationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SimulationStatusRepository $statusRepo
    ) {}

    /**
     * Point d'entrée pour le contrôleur : gère la création ou la mise à jour discrète
     */
    public function getOrCreateSimulation(User $user): Simulation
    {
        $simulation = $user->getSimulation();

        if (!$simulation) {
            return $this->createFirstSimulation($user);
        }

        // On vérifie si on doit marquer une activité, 
        // mais SEULEMENT si le dossier est toujours "OPEN"
        $this->addUpdateStepIfNecessary($simulation, $user);

        return $simulation;
    }

    /**
     * Création initiale du dossier
     */
    private function createFirstSimulation(User $user): Simulation
    {
        $simulation = new Simulation();
        $simulation->setUser($user);
        
        $statusOpen = $this->statusRepo->findOneBy(['code' => 'OPEN']); //! comme dans service.yaml
        $simulation->setStatus($statusOpen);

        $this->em->persist($simulation);
        
        // Historique : Création
        $this->addStep($simulation, 'OPEN', $user); //! comme dans service.yaml
        
        $this->em->flush();

        return $simulation;
    }

    /**
     * Ajoute une étape d'activité si le dossier est libre (OPEN) et que 24h ont passé
     */
    private function addUpdateStepIfNecessary(Simulation $simulation, User $user): void
    {
        // SÉCURITÉ 1 : On ne touche à l'historique que si le dossier est "OPEN"
        // Si un notaire l'a réservé, on ne crée pas de step de mise à jour client.
        if ($simulation->getStatus()?->getCode() !== 'OPEN') { //! comme dans service.yaml
            return;
        }

        $lastStep = $simulation->getSimulationSteps()->first(); // Plus récent (via OrderBy DESC)
        $limitDate = new \DateTimeImmutable('-24 hours');

        // SÉCURITÉ 2 : Délai de 24h pour éviter le spam
        if (!$lastStep || $lastStep->getCreatedAt() < $limitDate) {
            $this->addStep($simulation, 'OPEN', $user);
            $this->em->flush();
        }
    }

    /**
     * Méthode générique pour ajouter n'importe quelle étape (utilisable aussi par le notaire plus tard)
     */
    public function addStep(Simulation $simulation, string $statusCode, ?User $author = null): SimulationStep
    {
        $status = $this->statusRepo->findOneBy(['code' => $statusCode]);
        
        if (!$status) {
            throw new \Exception("Le code statut '$statusCode' n'existe pas.");
        }

        $step = new SimulationStep();
        $step->setSimulation($simulation);
        $step->setStatus($status);
        $step->setChangedBy($author); 
        $step->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($step);
        
        // On synchronise le statut de la simulation
        $simulation->setStatus($status);

        return $step;
    }
}