<?php

namespace App\Entity;

use App\Contract\TenantAwareInterface;
use App\Entity\Trait\HasBibliaReferenceTrait;
use App\Repository\StudyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: StudyRepository::class)]
#[ORM\Table(name: 'study')]
#[ORM\Index(name: 'study_biblia_idx', columns: ['biblia_book_id', 'biblia_chapter', 'biblia_verse_start', 'biblia_verse_end'])]
#[Vich\Uploadable]
class Study implements TenantAwareInterface
{
    use HasBibliaReferenceTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    /** Imagem principal (capa) do estudo */
    #[Vich\UploadableField(mapping: 'study_cover', fileNameProperty: 'coverImage')]
    private ?File $coverImageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $coverImageUpdatedAt = null;

    /** Rich-text description */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** HTML content: referências, links, tabelas, etc. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $materialsHtml = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: StudyMaterial::class, mappedBy: 'study', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $materials;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->materials = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getCoverImageFile(): ?File { return $this->coverImageFile; }
    public function setCoverImageFile(?File $file): static
    {
        $this->coverImageFile = $file;
        if ($file) { $this->coverImageUpdatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getCoverImage(): ?string { return $this->coverImage; }
    public function setCoverImage(?string $coverImage): static { $this->coverImage = $coverImage; return $this; }

    public function getCoverImageUpdatedAt(): ?\DateTimeImmutable { return $this->coverImageUpdatedAt; }
    public function setCoverImageUpdatedAt(?\DateTimeImmutable $coverImageUpdatedAt): static { $this->coverImageUpdatedAt = $coverImageUpdatedAt; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getMaterialsHtml(): ?string { return $this->materialsHtml; }
    public function setMaterialsHtml(?string $materialsHtml): static { $this->materialsHtml = $materialsHtml; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    /** @return Collection<int, StudyMaterial> */
    public function getMaterials(): Collection { return $this->materials; }
}
