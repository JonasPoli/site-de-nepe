<?php

namespace App\DataFixtures;

use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Seed data for local development:
 *   - Tenant A (127.0.0.1) + Tenant B (localhost)
 *   - SuperAdmin user (no tenant)
 *   - Admin user linked to Tenant A
 *
 * Run: php bin/console doctrine:fixtures:load --no-interaction
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        // ---- Tenants -------------------------------------------------------

        $tenantA = new Tenant();
        $tenantA->setDomain('127.0.0.1');
        $tenantA->setName('NEPE Brasil — Local');
        $tenantA->setPrimaryColor('#0044cc');
        $tenantA->setSecondaryColor('#ffaa00');
        $tenantA->setContactEmail('contato@nepebrasil.org');
        $tenantA->setTheme('nepe');
        $tenantA->setRequiredApprovals(2);
        $manager->persist($tenantA);

        $tenantB = new Tenant();
        $tenantB->setDomain('localhost');
        $tenantB->setName('NEPE Brasil — Localhost');
        $tenantB->setPrimaryColor('#008800');
        $tenantB->setSecondaryColor('#333333');
        $tenantB->setContactEmail('dev@localhost');
        $tenantB->setTheme('moderno');
        $tenantB->setRequiredApprovals(1);
        $manager->persist($tenantB);

        $manager->flush(); // flush tenants first so they get IDs

        // ---- SuperAdmin (no tenant) ----------------------------------------

        $superAdmin = new User();
        $superAdmin->setUsername('superadmin');
        $superAdmin->setName('Super Administrador');
        $superAdmin->setEmail('superadmin@nepebrasil.org');
        $superAdmin->setWorkGroup(0);
        $superAdmin->setTenant(null); // no tenant = SuperAdmin
        $superAdmin->setPassword($this->hasher->hashPassword($superAdmin, 'superadmin123'));
        $manager->persist($superAdmin);

        // ---- Admin of Tenant A --------------------------------------------

        $adminA = new User();
        $adminA->setUsername('admin');
        $adminA->setName('Administrador Tenant A');
        $adminA->setEmail('admin@nepebrasil.org');
        $adminA->setWorkGroup(0); // workGroup 0 + tenant set = Admin
        $adminA->setTenant($tenantA);
        $adminA->setPassword($this->hasher->hashPassword($adminA, 'admin123'));
        $manager->persist($adminA);

        // ---- Editor for Tenant A ------------------------------------------

        $editorA = new User();
        $editorA->setUsername('editor');
        $editorA->setName('Editor Tenant A');
        $editorA->setEmail('editor@nepebrasil.org');
        $editorA->setWorkGroup(1); // Editor
        $editorA->setTenant($tenantA);
        $editorA->setPassword($this->hasher->hashPassword($editorA, 'editor123'));
        $manager->persist($editorA);

        // ---- Reviewer for Tenant A ----------------------------------------

        $reviewerA = new User();
        $reviewerA->setUsername('revisor');
        $reviewerA->setName('Revisor Tenant A');
        $reviewerA->setEmail('revisor@nepebrasil.org');
        $reviewerA->setWorkGroup(2); // Reviewer
        $reviewerA->setTenant($tenantA);
        $reviewerA->setPassword($this->hasher->hashPassword($reviewerA, 'revisor123'));
        $manager->persist($reviewerA);

        $manager->flush();
    }
}
