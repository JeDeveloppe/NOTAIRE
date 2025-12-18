<?php

namespace App\Service;

use App\Entity\City;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Style\SymfonyStyle;

class CityImportService 
{
    private const BATCH_SIZE = 500;
    private const RELATIVE_CSV_PATH = '/import/communes-france-2025.csv'; 
    private string $projectDir; 

    public function __construct(
        private EntityManagerInterface $em,
        private CityRepository $cityRepository,
        string $kernelProjectDir 
    ) {
        $this->projectDir = $kernelProjectDir;
    }

    public function importCitiesOfFrance(?SymfonyStyle $io = null): void
    {
        ini_set('memory_limit', -1);
        $io?->title('Importation des villes Françaises');

        // ÉTAPE 1 : VIDER LA TABLE CITY
        $this->truncateCityTable($io);

        $filePath = $this->projectDir . self::RELATIVE_CSV_PATH; 

        if (!file_exists($filePath)) {
            $io?->error(sprintf('Fichier CSV introuvable: %s. Veuillez le placer dans le dossier %s/import/', self::RELATIVE_CSV_PATH, $this->projectDir));
            return;
        }
        
        $records = $this->readCsvFile($filePath);
        $count = iterator_count($records);
        $records = $this->readCsvFile($filePath); // Reset iterator
        
        $io?->progressStart($count);
        $i = 0; 
        $j = 0; 
        
        foreach ($records as $arrayVilleCommune) {
            $i++;
            $io?->progressAdvance();
            
            // Gestion des warnings
            $missingFields = [];
            if (empty($arrayVilleCommune['code_insee'])) { $missingFields[] = 'code_insee'; }
            if (empty($arrayVilleCommune['nom_standard'])) { $missingFields[] = 'nom_standard'; }
            if (empty($arrayVilleCommune['codes_postaux'])) { $missingFields[] = 'codes_postaux'; }
            if (empty($arrayVilleCommune['population'])) { $missingFields[] = 'population'; }
            if (empty($arrayVilleCommune['latitude_mairie'])) { $missingFields[] = 'latitude_mairie'; }
            if (empty($arrayVilleCommune['longitude_mairie'])) { $missingFields[] = 'longitude_mairie'; }


            if (!empty($missingFields)) {
                $io?->warning(sprintf(
                    'Ligne %d ignorée. Données manquantes: [%s] (Commune: %s)', 
                    $i, 
                    implode(', ', $missingFields), 
                    $arrayVilleCommune['nom_standard'] ?? 'Inconnu'
                ));
                continue;
            }
            
            $postalCodes = explode(',', $arrayVilleCommune['codes_postaux']);
            
            foreach ($postalCodes as $codePostal) {
                $codePostal = trim($codePostal);
                
                if (empty($codePostal)) {
                    continue;
                }

                $city = $this->createOrUpdateCity($arrayVilleCommune, $codePostal);
                $this->em->persist($city);
                $j++;
            }
            
            // Vidage en lot
            if (($j % self::BATCH_SIZE) === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }
        
        // Vidage final
        $this->em->flush();
        $this->em->clear();
        
        $io?->progressFinish();
        $io?->success(sprintf('Importation terminée. %d lignes de communes lues, %d enregistrements de villes/CP créés.', $i, $j));
    }
    
    /**
     * Vide la table City avant l'importation.
     */
    private function truncateCityTable(?SymfonyStyle $io = null): void
    {
        $io?->note('Vidage de la table City en cours...');
        
        $connection = $this->em->getConnection();
        
        // 1. Désactiver la vérification des contraintes de clés étrangères (si nécessaire)
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // 2. TRUNCATE (plus rapide que le DELETE FROM)
        $platform = $connection->getDatabasePlatform();
        $tableName = $this->em->getClassMetadata(City::class)->getTableName();
        
        // Récupérer la requête TRUNCATE (avec cascade si spécifié)
        // Attention : la méthode getTruncateTableSQL ne prend pas toujours d'argument "true" pour CASCADE.
        // Pour MySQL, TRUNCATE ne supporte pas CASCADE. On se fie à FOREIGN_KEY_CHECKS = 0.
        $sql = $platform->getTruncateTableSQL($tableName); 
        
        $connection->executeStatement($sql); // Utilisation de executeStatement()

        // 3. Réactiver la vérification des contraintes
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        
        $io?->note('Table City vidée.');
    }

    private function readCsvFile(string $path): \Iterator
    {
        // ... (lecture CSV inchangée)
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('Fichier introuvable: %s.', $path)); 
        }

        $csv = Reader::createFromPath($path, 'r'); 
        
        $csv->setDelimiter(',');
        $csv->setHeaderOffset(0);
        
        $csv->skipInputBOM(); 
        $csv->addStreamFilter('convert.iconv.UTF-8/UTF-8//IGNORE');

        return $csv->getRecords();
    }

    private function createOrUpdateCity(array $arrayCommune, string $postalCode): City
    {
        // Recherche par INSEE Code et Postal Code
        $city = $this->cityRepository->findOneBy([
            'inseeCode' => $arrayCommune['code_insee'], 
            'postalCode' => $postalCode
        ]);

        if (!$city) {
            $city = new City();
        }

        $city->setName($arrayCommune['nom_standard']) 
             ->setPostalcode($postalCode) 
             ->setInseeCode((int) $arrayCommune['code_insee']) 
             ->setPopulation((int) $arrayCommune['population'] ?? 0);
        $city->setTownHallLatitude($arrayCommune['latitude_mairie'] ?? null);
        $city->setTownHallLongitude($arrayCommune['longitude_mairie'] ?? null);

        return $city;
    }
}