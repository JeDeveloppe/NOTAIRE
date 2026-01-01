<?php

namespace App\Service;

use App\Entity\Notary;
use App\Entity\Subscription;

class OfferService
{
    /**
     * Calcule le nombre total de slots (codes postaux) autorisés pour une étude
     */

    public function getTotalAllowedSectors(Notary $notary, ?Subscription $activeSub = null): int
    {
        // 1. Si on a passé une subscription (ex: simulation DEV), on l'utilise direct
        if ($activeSub !== null) {
            return $this->calculateSubscriptionQuota($activeSub);
        }

        // 2. Sinon, on cherche les abonnements actifs en base de données
        $total = 0;
        foreach ($notary->getSubscriptions() as $subscription) {
            // Vérifie si l'abonnement est bien actif (selon tes critères)
            if ($subscription->getStatus() === 'active') {
                $total += $this->calculateSubscriptionQuota($subscription);
            }
        }

        return $total;
    }

    /**
     * Centralise le calcul : Offre de base + Addon éventuel
     */
    private function calculateSubscriptionQuota(Subscription $subscription): int
    {
        $quota = 0;

        if ($subscription->getOffer()) {
            $quota += $subscription->getOffer()->getBaseNotariesCount();
        }

        if ($subscription->getAddon()) {
            $quota += $subscription->getAddon()->getBaseNotariesCount();
        }

        return $quota;
    }
}
