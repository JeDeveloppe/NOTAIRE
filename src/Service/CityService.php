<?php

namespace App\Service;

use App\Entity\City;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

class CityService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CityRepository $cityRepository,
        private CountryRepository $countryRepository,
        private SluggerInterface $slugger,
        private string $projectDir 
    ) {}

    public function importCitiesOfFrance(SymfonyStyle $io): void
    {
        // On lève les limites PHP pour le traitement
        ini_set('memory_limit', '-1');
        set_time_limit(0); // Pas de limite de temps pour l'import

        $io->title('Importation rapide des villes Françaises');

        $path = $this->projectDir . '/data/france/cities.csv';
        if (!file_exists($path)) {
            $io->error("Le fichier CSV est introuvable : $path");
            return;
        }

        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);
        $csv->setDelimiter(',');

        $france = $this->countryRepository->findOneBy(['isoCode' => 'FR']);
        if (!$france) {
            $io->error("Le pays 'FR' est introuvable.");
            return;
        }

        $records = $csv->getRecords();
        $io->progressStart(36000); 

        $batchSize = 1000; 
        $i = 1;

        foreach ($records as $arrayVille) {
            // Re-fetch de l'entité France uniquement après un clear()
            if (!$this->em->contains($france)) {
                $france = $this->countryRepository->findOneBy(['isoCode' => 'FR']);
            }

            // CRÉATION DIRECTE (Plus de SELECT findOneBy pour la vitesse)
            $city = new City();
            $city->setInseeCode($arrayVille['insee_code'])
                ->setCountry($france)
                ->setName($arrayVille['name'])
                ->setLatitude($arrayVille['gps_lat'])
                ->setLongitude($arrayVille['gps_lng'])
                ->setPostalCode($arrayVille['zip_code'])
                ->setDepartmentCode($arrayVille['department_code'])
                ->setSlug($this->slugger->slug($arrayVille['name'])->lower());

            $this->em->persist($city);

            if (($i % $batchSize) === 0) {
                $this->em->flush();
                $this->em->clear(); 
                $io->progressAdvance($batchSize);
            }
            $i++;
        }

        $this->em->flush();
        $this->em->clear();
        
        $io->progressFinish();
        $io->success('Importation massive terminée !');
    }
}