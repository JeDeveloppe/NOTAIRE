<?php

namespace App\Service;

use App\Entity\Notary;

class OfferService
{
    /**
     * Calcule le nombre total de slots (codes postaux) autorisés pour une étude
     */
    public function getTotalAllowedSectors(Notary $notary): int
    {
        $total = 0;
        foreach ($notary->getSubscriptions() as $subscription) {
            if ($subscription->isActive() && $subscription->getStatus() === 'active') {
                $total += $subscription->getOffer()->getBaseSectorsCount();
            }
        }
        return $total;
    }
}