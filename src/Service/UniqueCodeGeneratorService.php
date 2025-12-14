<?php

namespace App\Service;

use App\Repository\UserRepository;
use Exception; // Pour la gestion de random_int

class UniqueCodeGeneratorService
{
    // Caractères sans ambiguïté visuelle : 2, 3, 4, 5, 6, 7, 8, 9, A-Z (sans I, L, O, Q)
    public const CHARACTERS = '23456789ABCDEFGHJKMNPRTUVWXY'; 
    public const CODE_LENGTH = 8; // Longueur brute (sans le tiret)

    // Injection du UserRepository pour vérifier l'unicité
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Génère un code unique de 8 caractères structuré en XXXX-XXXX, 
     * en vérifiant qu'il n'existe pas déjà dans la table User.
     * * @return string Le code unique formaté.
     * @throws Exception Si la génération aléatoire échoue.
     */
    public function generateUniqueCode(): string
    {
        do {
            $codeRaw = $this->generateRandomCode(self::CODE_LENGTH);
            // Formate le code en insérant le tiret au milieu
            $code = substr($codeRaw, 0, 4) . '-' . substr($codeRaw, 4);
            
            // Vérifie l'unicité dans la table User
            // Nous utilisons findOneBy sur le champ uniqueCode
        } while ($this->userRepository->findOneBy(['uniqueCode' => $code]));

        return $code;
    }

    /**
     * Génère une chaîne aléatoire de la longueur spécifiée.
     *
     * @param int $length
     * @return string
     * @throws Exception
     */
    private function generateRandomCode(int $length): string
    {
        $code = '';
        $max = strlen(self::CHARACTERS) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARACTERS[random_int(0, $max)];
        }
        return $code;
    }
}