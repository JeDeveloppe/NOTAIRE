<?php

namespace App\Service;

use App\Entity\Country;
use App\Repository\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CountryInitializerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CountryRepository $countryRepository
    ) {}

    public function initializeCountries(?SymfonyStyle $io = null): void
    {
        $countries = [
            ['name' => 'France', 'iso' => 'FR'],
            ['name' => 'Belgique', 'iso' => 'BE'],
            ['name' => 'Suisse', 'iso' => 'CH'],
            ['name' => 'Luxembourg', 'iso' => 'LU'],
        ];

        foreach ($countries as $data) {
            $country = $this->countryRepository->findOneBy(['isoCode' => $data['iso']]);

            if (!$country) {
                $country = new Country();
                $country->setName($data['name']);
                $country->setIsoCode($data['iso']);
                $this->em->persist($country);
                
                if ($io) $io->writeln("🌍 Pays créé : {$data['name']}");
            } else {
                if ($io) $io->writeln("✅ Pays déjà existant : {$data['name']}");
            }
        }

        $this->em->flush();
    }
}