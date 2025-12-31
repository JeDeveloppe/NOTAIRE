<?php

namespace App\Controller;

use App\Repository\CityRepository;
use App\Repository\SelectedZipCodeRepository;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiController extends AbstractController
{

    #[Route('/api/check-zip/{zipCode}', name: 'api_check_zip', methods: ['GET'])]
    public function checkZip(
        string $zipCode,
        CityRepository $cityRepository,
        SelectedZipCodeRepository $zipRepo
    ): JsonResponse {
        $cities = $cityRepository->findBy(['postalCode' => $zipCode], ['name' => 'ASC']);

        if (empty($cities)) {
            return new JsonResponse(['status' => 'not_found'], 404);
        }

        $citiesData = [];
        $globalAvailable = false;

        foreach ($cities as $city) {
            // 1. On récupère le quota de la ville
            $max = $city->getMaxNotariesCount() ?? 2;

            // 2. On compte les réservations uniquement pour CETTE ville
            $count = (int) $zipRepo->createQueryBuilder('s')
                ->select('count(s.id)')
                ->where('s.city = :city')
                ->setParameter('city', $city)
                ->getQuery()
                ->getSingleScalarResult();

            $remaining = max(0, $max - $count);

            if ($remaining > 0) {
                $globalAvailable = true;
            }

            $citiesData[] = [
                'name' => $city->getName(),
                'max' => $max,
                'remaining' => $remaining,
                'isFull' => $remaining <= 0
            ];
        }

        return new JsonResponse([
            'status' => 'ok',
            'postalCode' => $zipCode,
            'cities' => $citiesData,
            'available' => $globalAvailable, // Vrai s'il reste au moins une place dans une des villes
        ]);
    }
}
