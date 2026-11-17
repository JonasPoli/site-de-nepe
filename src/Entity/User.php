<?php

namespace App\Entity;

use App\Enum\PanelRole;
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

    /**
     * O papel mostrado no painel, pela mesma regra de getRoles(): "Super Admin" exige
     * estar sem NEPE E no grupo 0. Sem NEPE fora do grupo 0 é cadastro inconsistente.
     */
    public function panelRole(): PanelRole
    {
        if ($this->tenant === null) {
            return $this->workGroup === 0 ? PanelRole::SuperAdmin : PanelRole::Inconsistent;
        }

        return match ($this->workGroup) {
            0 => PanelRole::Admin,
            1 => PanelRole::Editor,
            2 => PanelRole::Reviewer,
            default => PanelRole::Inconsistent,
        };
    }

    /** Iniciais para a lista quando o usuário não tem foto */
    public function initials(): string
    {
        $partes = preg_split('~\s+~', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($partes === []) {
            return '';
        }

        $primeira = mb_substr($partes[0], 0, 1);
        $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';

        return mb_strtoupper($primeira . $ultima);
    }
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
     * SplFileInfo e não pode ser serializado: fica de fora.
     *
     * Usa __sleep (formato clássico do PHP) de propósito, e não __serialize: assim o
     * conteúdo gravado continua igual ao de antes desta mudança, e as sessões abertas
     * antes do deploy seguem válidas. Sem isso, o provider recebe um usuário sem id
     * ("You cannot refresh a user from the EntityUserProvider...") e o painel dá 500.
     *
     * @return list<string>
     */
    public function __sleep(): array
    {
        return ['id', 'username', 'name', 'email', 'whatsapp', 'photo', 'photoUpdatedAt', 'workGroup', 'tenant', 'roles', 'password'];
    }

    /**
     * Só para as sessões gravadas no formato de __serialize (array com chaves simples).
     * Aceita também o formato clássico, cujas chaves vêm com o nome da classe embutido.
     */
    public function __unserialize(array $data): void
    {
        $valor = function (string $propriedade) use ($data) {
            foreach ([$propriedade, "\0" . self::class . "\0" . $propriedade, "\0*\0" . $propriedade] as $chave) {
                if (array_key_exists($chave, $data)) {
                    return $data[$chave];
                }
            }

            return null;
        };

        $this->id             = $valor('id');
        $this->username       = $valor('username');
        $this->name           = $valor('name') ?? '';
        $this->email          = $valor('email');
        $this->whatsapp       = $valor('whatsapp');
        $this->photo          = $valor('photo');
        $this->photoUpdatedAt = $valor('photoUpdatedAt');
        $this->workGroup      = (int) $valor('workGroup');
        $this->tenant         = $valor('tenant');
        $this->roles          = $valor('roles') ?? [];
        $this->password       = $valor('password');
        $this->photoFile      = null;
    }

    public function __toString(): string { return $this->name ?: (string) $this->username; }
}
