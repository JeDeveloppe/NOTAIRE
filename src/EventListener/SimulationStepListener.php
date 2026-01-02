<?php

namespace App\EventListener;

use App\Entity\SimulationStep;
use App\Entity\Notary;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postPersist, method: 'updateNotaryScore', entity: SimulationStep::class)]
class SimulationStepListener
{ // App/EventListener/SimulationStepListener.php

    public function updateNotaryScore(SimulationStep $step, LifecycleEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();

        // On récupère le notaire qui a fait l'action
        $notary = $step->getChangeByNotary();

        // Si pas de notaire (ex: étape "Disponible" par admin), on ne fait rien
        if (!$notary) {
            return;
        }

        $points = $step->getStatus()->getPoints();

        if ($points !== 0) {
            $newScore = ($notary->getScore() ?? 0) + $points;
            $notary->setScore($newScore);

            // On dit explicitement à Doctrine de surveiller cette entité
            $entityManager->persist($notary);

            // On force le flush UNIQUEMENT pour le notaire. 
            // Cela déclenche une petite transaction SQL isolée.
            $entityManager->flush($notary);
        }
    }
}
