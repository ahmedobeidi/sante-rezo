<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408144016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE doctor (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, specialty_id INT DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, profile_image VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_1FC0F36AA76ED395 (user_id), INDEX IDX_1FC0F36A9A353316 (specialty_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE doctor ADD CONSTRAINT FK_1FC0F36AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE doctor ADD CONSTRAINT FK_1FC0F36A9A353316 FOREIGN KEY (specialty_id) REFERENCES specialty (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE doctor DROP FOREIGN KEY FK_1FC0F36AA76ED395');
        $this->addSql('ALTER TABLE doctor DROP FOREIGN KEY FK_1FC0F36A9A353316');
        $this->addSql('DROP TABLE doctor');
    }
}
