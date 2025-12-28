# 📜 Système de Gestion Notariale & Optimisation Fiscale

Ce projet est une application Symfony dédiée à la gestion patrimoniale familiale. Il permet de cartographier les relations entre les membres d'une famille, de suivre l'historique des donations et de simuler les capacités de transmission futures en fonction de la fiscalité française (loi des 15 ans).

## 🚀 Fonctionnalités Clés

* **Analyse des Abattements** : Suivi détaillé des droits de donation utilisés et expirés pour chaque binôme donateur/bénéficiaire.
* **Simulation Fiscale (15 ans)** : Calcul automatique de la régénération des plafonds fiscaux selon le calendrier légal.
* **Détection Intelligente de Parenté** : Identification automatique des liens (Enfants, Petits-enfants, Collatéraux) pour appliquer le bon barème fiscal.
* **Plan de Transmission** : Tableau de bord affichant la capacité de transmission immédiate et les économies d'impôts potentielles.

## 🛠 Architecture Technique

### Services Principaux

1. **DonationService**
   * Gère la logique de calcul des abattements consommés sur les 15 dernières années.
   * Identifie le code de relation (relationship_code) entre deux personnes.
   * Simule le montant maximal transmissible sans impôts à une date donnée.

2. **TaxOptimizationService**
   * Analyse l'historique pour identifier les "opportunités manquées" (abattements expirés non saturés).
   * Génère le plan de transmission global pour l'ensemble des membres de la famille.

### Modèle de Données
Le système s'appuie sur une structure relationnelle où chaque Personne possède des parents, des enfants, et un historique de Donations.

## 📊 Fiscalité Intégrée
Le projet intègre les règles fiscales de 2025, notamment :

* **Abattements de ligne directe** : Parent-Enfant (100 000 €), Petit-Enfant (31 865 €).
* **Dons Familiaux de Sommes d'Argent** : Dispositif "Sarkozy" de 31 865 € sous conditions d'âge.
* **Règle du Rappel Fiscal** : Gestion du délai de 15 ans entre deux donations pour bénéficier à nouveau des abattements.

## 💻 Installation & Configuration

1. **Cloner le projet** :
   git clone https://github.com/JeDeveloppe/NOTAIRE.git

2. **Installer les dépendances** :
   composer install

3. **Configurer les variables d'environnement** :
   Éditez votre fichier `.env` pour configurer la base de données et les accès administrateur :
   - DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name"
   - ADMIN_EMAIL=admin@exemple.com
   - ADMIN_PASSWORD=votre_mot_de_passe_secret

4. **Initialiser le système** :
   Utilisez la commande dédiée pour créer la base de données, importer les règles fiscales et générer un jeu de test :
   php bin/console app:init

   > **Note :** Cette commande crée automatiquement la **Famille Dubois** (Robert le grand-père, Jean et Marie les enfants, Marc le petit-fils) ainsi qu'un historique de donations pour tester immédiatement les fonctionnalités d'optimisation.

## 📝 Usage

Le tableau de bord d'optimisation est accessible via la route `app_optimization_dashboard`. Vous pouvez simuler une situation à une date future pour anticiper la libération de nouveaux abattements.