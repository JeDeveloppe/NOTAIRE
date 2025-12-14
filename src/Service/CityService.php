<?php
// src/Service/CityCodeLookupService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Exception;
use Psr\Log\LoggerInterface;

class CityService
{
    // Utiliser l'API gouvernementale française de préférence (BAN)
    private const API_ENDPOINT = 'https://api-adresse.data.gouv.fr/search/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Valide et récupère la ville et le code postal à partir d'une saisie utilisateur.
     *
     * @param string $input L'entrée de l'utilisateur (peut être CP, ville, ou les deux).
     * @return array|null Un tableau ['postalCode' => 'XXXXX', 'city' => 'Ville Normale'] ou null si non trouvé.
     * @throws Exception Si l'API échoue ou retourne une réponse inattendue.
     */
    public function lookupCityAndCode(string $input): ?array
    {
        if (empty($input)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_ENDPOINT, [
                'query' => [
                    'q' => $input,
                    'limit' => 1, // On cherche le résultat le plus pertinent
                ],
            ]);

            $content = $response->toArray();

            if (empty($content['features'])) {
                return null; // Aucune correspondance trouvée
            }
            
            // On prend le premier résultat pertinent (le plus fiable)
            $properties = $content['features'][0]['properties'];
            
            // Vérification des données essentielles
            if (!isset($properties['postcode']) || !isset($properties['city'])) {
                $this->logger->warning('API Géocodage a retourné un résultat sans CP ou Ville', ['properties' => $properties]);
                return null;
            }

            return [
                // Normalisation: majuscule/minuscule pour la ville, nettoyage du CP
                'postalCode' => trim($properties['postcode']),
                'city' => ucwords(strtolower(trim($properties['city']))), 
            ];

        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'appel à l\'API de géocodage: ' . $e->getMessage());
            // Dans un contexte d'inscription, mieux vaut ne pas bloquer l'utilisateur 
            // mais l'informer que la validation est impossible. Ici, nous retournons null.
            return null; 
        }
    }
}