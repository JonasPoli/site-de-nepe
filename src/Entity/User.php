<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
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
     */
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
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

        if ($this->tenant === null && $this->workGroup === 0) {
            $roles[] = 'ROLE_SUPER_ADMIN';
            $roles[] = 'ROLE_ADMIN'; // SuperAdmin can also access /admin
        } elseif ($this->workGroup === 0) {
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

    public function __toString(): string { return $this->name ?: (string) $this->username; }
}
