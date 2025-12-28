<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228193244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE donation (id INT AUTO_INCREMENT NOT NULL, amount INT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(50) NOT NULL, tax_paid INT NOT NULL, donor_id INT NOT NULL, beneficiary_id INT NOT NULL, INDEX IDX_31E581A03DD7B7A7 (donor_id), INDEX IDX_31E581A0ECCAAFA0 (beneficiary_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE donation_rule (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, allowance_amount INT NOT NULL, frequency_years INT NOT NULL, donor_max_age INT NOT NULL, receiver_min_age INT NOT NULL, is_cumulative TINYINT NOT NULL, tax_system VARCHAR(100) DEFAULT NULL, relationship_id INT NOT NULL, INDEX IDX_574B87E62C41D668 (relationship_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, firstname VARCHAR(255) NOT NULL, lastname VARCHAR(255) NOT NULL, birthdate DATETIME NOT NULL, death_date DATETIME DEFAULT NULL, gender VARCHAR(15) NOT NULL, owner_id INT NOT NULL, INDEX IDX_34DCD1767E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE person_relations (person_source INT NOT NULL, person_target INT NOT NULL, INDEX IDX_AC455CD2C32F4FC5 (person_source), INDEX IDX_AC455CD2DACA1F4A (person_target), PRIMARY KEY (person_source, person_target)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE relationship (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_31E581A03DD7B7A7 FOREIGN KEY (donor_id) REFERENCES person (id)');
        $this->addSql('ALTER TABLE donation ADD CONSTRAINT FK_31E581A0ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES person (id)');
        $this->addSql('ALTER TABLE donation_rule ADD CONSTRAINT FK_574B87E62C41D668 FOREIGN KEY (relationship_id) REFERENCES relationship (id)');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_34DCD1767E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE person_relations ADD CONSTRAINT FK_AC455CD2C32F4FC5 FOREIGN KEY (person_source) REFERENCES person (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE person_relations ADD CONSTRAINT FK_AC455CD2DACA1F4A FOREIGN KEY (person_target) REFERENCES person (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_31E581A03DD7B7A7');
        $this->addSql('ALTER TABLE donation DROP FOREIGN KEY FK_31E581A0ECCAAFA0');
        $this->addSql('ALTER TABLE donation_rule DROP FOREIGN KEY FK_574B87E62C41D668');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_34DCD1767E3C61F9');
        $this->addSql('ALTER TABLE person_relations DROP FOREIGN KEY FK_AC455CD2C32F4FC5');
        $this->addSql('ALTER TABLE person_relations DROP FOREIGN KEY FK_AC455CD2DACA1F4A');
        $this->addSql('DROP TABLE donation');
        $this->addSql('DROP TABLE donation_rule');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE person_relations');
        $this->addSql('DROP TABLE relationship');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
