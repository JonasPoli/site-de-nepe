<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[Vich\Uploadable]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $email = null;

    /** WhatsApp de contato do usuário: dado público, pode sair no site e na API. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $whatsapp = null;

    /**
     * Foto do usuário. O arquivo em si é tratado pelo Vich (mapping user_photo);
     * photoUpdatedAt existe para o Doctrine ver uma mudança quando só o arquivo troca.
     */
    #[Vich\UploadableField(mapping: 'user_photo', fileNameProperty: 'photo')]
    private ?File $photoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $photoUpdatedAt = null;

    /**
     * WorkGroup defines the user's role within a tenant:
     *  0 = Admin (full access to tenant content; or SuperAdmin if tenant is null)
     *  1 = Editor (can create/edit articles)
     *  2 = Reviewer (can approve articles, send comments)
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $workGroup = 0;

    /**
     * Null = SuperAdmin (global). Set = scoped to that tenant only.
     * Deleting the tenant deletes its users: with SET NULL they would become SuperAdmins.
     */
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    public function getId(): ?int { return $this->id; }

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getWhatsapp(): ?string { return $this->whatsapp; }
    public function setWhatsapp(?string $whatsapp): static { $this->whatsapp = $whatsapp; return $this; }

    public function getPhotoFile(): ?File { return $this->photoFile; }
    public function setPhotoFile(?File $photoFile): static
    {
        $this->photoFile = $photoFile;
        if ($photoFile !== null) {
            // Sem um campo alterado o Doctrine não vê mudança e o Vich não grava o arquivo novo
            $this->photoUpdatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getPhoto(): ?string { return $this->photo; }
    public function setPhoto(?string $photo): static { $this->photo = $photo; return $this; }

    public function getPhotoUpdatedAt(): ?\DateTimeImmutable { return $this->photoUpdatedAt; }
    public function setPhotoUpdatedAt(?\DateTimeImmutable $photoUpdatedAt): static { $this->photoUpdatedAt = $photoUpdatedAt; return $this; }

    public function getWorkGroup(): int { return $this->workGroup; }
    public function setWorkGroup(int $workGroup): static { $this->workGroup = $workGroup; return $this; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }

    public function isSuperAdmin(): bool { return $this->tenant === null && $this->workGroup === 0; }
    public function isAdmin(): bool { return $this->workGroup === 0; }
    public function isEditor(): bool { return $this->workGroup === 1; }
    public function isReviewer(): bool { return $this->workGroup === 2; }

    /** @see UserInterface */
    public function getUserIdentifier(): string { return (string) $this->username; }

    /**
     * @see UserInterface
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        // ROLE_NEPE_ADMIN separa o administrador do NEPE (grupo 0) de editores e revisores:
        // é o papel exigido para gerir usuários e configurações do site. Calculado, não gravado.
        if ($this->tenant === null && $this->workGroup === 0) {
            $roles[] = 'ROLE_SUPER_ADMIN';
            $roles[] = 'ROLE_NEPE_ADMIN';
            $roles[] = 'ROLE_ADMIN'; // SuperAdmin can also access /admin
        } elseif ($this->workGroup === 0) {
            $roles[] = 'ROLE_NEPE_ADMIN';
            $roles[] = 'ROLE_ADMIN';
        } elseif ($this->workGroup === 1 || $this->workGroup === 2) {
            $roles[] = 'ROLE_EDITOR';
            $roles[] = 'ROLE_ADMIN'; // editors also see the admin panel
        }

        return array_unique($roles);
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    /** @see PasswordAuthenticatedUserInterface */
    public function getPassword(): string { return (string) $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    /** @see UserInterface */
    public function eraseCredentials(): void {}

    /**
     * O usuário logado é serializado na sessão a cada requisição. $photoFile é um
     * SplFileInfo e não pode ser serializado: fica de fora, como no Tenant.
     */
    public function __serialize(): array
    {
        return [
            'id'             => $this->id,
            'username'       => $this->username,
            'name'           => $this->name,
            'email'          => $this->email,
            'whatsapp'       => $this->whatsapp,
            'photo'          => $this->photo,
            'photoUpdatedAt' => $this->photoUpdatedAt,
            'workGroup'      => $this->workGroup,
            'tenant'         => $this->tenant,
            'roles'          => $this->roles,
            'password'       => $this->password,
            // photoFile intentionally excluded — it is not serializable
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id             = $data['id'] ?? null;
        $this->username       = $data['username'] ?? null;
        $this->name           = $data['name'] ?? '';
        $this->email          = $data['email'] ?? null;
        $this->whatsapp       = $data['whatsapp'] ?? null;
        $this->photo          = $data['photo'] ?? null;
        $this->photoUpdatedAt = $data['photoUpdatedAt'] ?? null;
        $this->workGroup      = $data['workGroup'] ?? 0;
        $this->tenant         = $data['tenant'] ?? null;
        $this->roles          = $data['roles'] ?? [];
        $this->password       = $data['password'] ?? null;
        $this->photoFile      = null;
    }

    public function __toString(): string { return $this->name ?: (string) $this->username; }
}
