<?php

namespace App\Service;

use App\Entity\NotaryOffice;
use App\Entity\SubscriptionType;
use App\Entity\NotarySubscription;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function activatePremium(NotaryOffice $office, int $durationMonths = 12): void
    {
        $subscription = $office->getNotarySubscription();
        $subscriptionType = $this->em->getRepository(SubscriptionType::class)
            ->findOneBy(['name' => 'Premium']); //! ATTENTION DOIT ETRE COMME DANS INITIALISATION => OK

        if (!$subscription) {
            $subscription = new NotarySubscription();
            $subscription->setNotaryOffice($office);
        }

        // Calcul de la date de fin : Aujourd'hui + X mois
        $startDate = new \DateTimeImmutable();
        $endDate = $startDate->modify("+$durationMonths months");

        $subscription->setActivedAt($startDate);
        $subscription->setEndsAt($endDate);
        $subscription->setSubscriptionType($subscriptionType);

        $this->em->persist($subscription);
        $this->em->flush();
    }
}