<?php

namespace App\Tests\Controller\admin;

use App\Entity\SuperTestFields;
use App\Repository\SuperTestFieldsRepository;
use App\Repository\TestDatabaseRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class SuperTestFieldsControllerTest extends WebTestCase
{
    private ?UserInterface $adminUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $this->adminUser = $userRepository->findOneBy(['email' => 'admin@example.com']); // Assuming an admin user with this email

        if (!$this->adminUser) {
            // Optionally create an admin user if it doesn't exist for testing
            // This would require more setup, so for now, we assume it exists.
            $this->fail('Admin user not found. Please create an admin user with email admin@example.com.');
        }
    }

    public function testIndex(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);
        $client->request('GET', '/admin/super/test/fields');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Super Test Fields');
    }

    public function testNew(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);
        $testDatabaseRepository = static::getContainer()->get(TestDatabaseRepository::class);
        $testDatabase = $testDatabaseRepository->findOneBy([]);

        if (!$testDatabase) {
            $this->markTestSkipped('No TestDatabase entity found to link to.');
        }

        $client->request('GET', '/admin/super/test/fields/new');

        $this->assertResponseIsSuccessful();

        $client->submitForm('Salvar', [
            'super_test_fields[SimpleInputText]' => 'Test Input',
            'super_test_fields[EditTextWithEditor]' => 'Test Editor Content',
            'super_test_fields[DateField]' => '2023-01-01',
            'super_test_fields[DateAndTimeField]' => '2023-01-01T12:00',
            'super_test_fields[ChoiceTypeFromList]' => 'Opção 1',
            'super_test_fields[ChoiceTypeFromEntity]' => $testDatabase->getId(),
            'super_test_fields[SinNaoInt]' => '1',
            'super_test_fields[BooleanTrueFalse]' => true,
            // 'super_test_fields[imageFile]' => ... (file uploads are complex in functional tests)
            'super_test_fields[SelectEnum]' => 'en',
            'super_test_fields[emailField]' => 'test@example.com',
            'super_test_fields[numeroSimples]' => '123',
        ]);

        $this->assertResponseRedirects('/admin/super/test/fields');
        $superTestFieldsRepository = static::getContainer()->get(SuperTestFieldsRepository::class);
        $this->assertCount(1, $superTestFieldsRepository->findAll());
    }

    public function testShow(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);
        $superTestFieldsRepository = static::getContainer()->get(SuperTestFieldsRepository::class);
        $superTestField = $superTestFieldsRepository->findOneBy([]);

        if (!$superTestField) {
            $this->markTestSkipped('No SuperTestFields entity found to show.');
        }

        $client->request('GET', sprintf('/admin/super/test/fields/%d', $superTestField->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Detalhes de Super Test Field');
    }

    public function testEdit(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);
        $superTestFieldsRepository = static::getContainer()->get(SuperTestFieldsRepository::class);
        $superTestField = $superTestFieldsRepository->findOneBy([]);

        if (!$superTestField) {
            $this->markTestSkipped('No SuperTestFields entity found to edit.');
        }

        $client->request('GET', sprintf('/admin/super/test/fields/%d/edit', $superTestField->getId()));

        $this->assertResponseIsSuccessful();

        $client->submitForm('Update', [
            'super_test_fields[SimpleInputText]' => 'Updated Input',
            'super_test_fields[EditTextWithEditor]' => 'Updated Editor Content',
            'super_test_fields[DateField]' => '2023-02-02',
            'super_test_fields[DateAndTimeField]' => '2023-02-02T13:00',
            'super_test_fields[ChoiceTypeFromList]' => 'Opção 2',
            'super_test_fields[SinNaoInt]' => '0',
            'super_test_fields[BooleanTrueFalse]' => false,
            'super_test_fields[SelectEnum]' => 'pt',
            'super_test_fields[emailField]' => 'updated@example.com',
            'super_test_fields[numeroSimples]' => '456',
        ]);

        $this->assertResponseRedirects('/admin/super/test/fields');
        $updatedSuperTestField = $superTestFieldsRepository->find($superTestField->getId());
        $this->assertSame('Updated Input', $updatedSuperTestField->getSimpleInputText());
    }

    public function testDelete(): void
    {
        $client = static::createClient();
        $client->loginUser($this->adminUser);
        $superTestFieldsRepository = static::getContainer()->get(SuperTestFieldsRepository::class);
        $superTestField = $superTestFieldsRepository->findOneBy([]);

        if (!$superTestField) {
            $this->markTestSkipped('No SuperTestFields entity found to delete.');
        }

        $client->request('POST', sprintf('/admin/super/test/fields/%d', $superTestField->getId()), [], [], [
            'HTTP_REFERER' => '/admin/super/test/fields',
        ]);

        $client->submitForm('Delete');

        $this->assertResponseRedirects('/admin/super/test/fields');
        $this->assertNull($superTestFieldsRepository->find($superTestField->getId()));
    }
}
