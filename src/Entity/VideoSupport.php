<?php

namespace App\Entity;

use App\Contract\TenantAwareInterface;
use App\Repository\VideoSupportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: VideoSupportRepository::class)]
#[Vich\Uploadable]
class VideoSupport implements TenantAwareInterface
{
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

    /** YouTube video ID — e.g. dQw4w9WgXcQ */
    #[ORM\Column(length: 30)]
    private string $youtubeId = '';

    /** Imagem personalizada para thumbnail (opcional) */
    #[Vich\UploadableField(mapping: 'video_thumb', fileNameProperty: 'customThumbnail')]
    private ?File $customThumbnailFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customThumbnail = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $customThumbnailUpdatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** HTML content with download links, PDFs, etc. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $materialsHtml = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: \App\Entity\VideoMaterial::class, mappedBy: 'video', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getYoutubeId(): string { return $this->youtubeId; }
    public function setYoutubeId(string $youtubeId): static { $this->youtubeId = $youtubeId; return $this; }

    public function getCustomThumbnailFile(): ?File { return $this->customThumbnailFile; }
    public function setCustomThumbnailFile(?File $file): static
    {
        $this->customThumbnailFile = $file;
        if ($file) { $this->customThumbnailUpdatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getCustomThumbnail(): ?string { return $this->customThumbnail; }
    public function setCustomThumbnail(?string $customThumbnail): static { $this->customThumbnail = $customThumbnail; return $this; }

    public function getCustomThumbnailUpdatedAt(): ?\DateTimeImmutable { return $this->customThumbnailUpdatedAt; }
    public function setCustomThumbnailUpdatedAt(?\DateTimeImmutable $customThumbnailUpdatedAt): static { $this->customThumbnailUpdatedAt = $customThumbnailUpdatedAt; return $this; }

    public function hasCustomThumbnail(): bool
    {
        return !empty($this->customThumbnail);
    }

    public function getYoutubeThumbnailUrl(): string
    {
        return $this->youtubeId !== '' ? sprintf('https://img.youtube.com/vi/%s/maxresdefault.jpg', $this->youtubeId) : '';
    }

    public function getThumbnailUrl(): string
    {
        if (!empty($this->customThumbnail)) {
            return '/uploads/video_thumb/' . $this->customThumbnail;
        }

        return $this->getYoutubeThumbnailUrl();
    }

    public function getEmbedUrl(): string
    {
        return sprintf('https://www.youtube.com/embed/%s', $this->youtubeId);
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getMaterialsHtml(): ?string { return $this->materialsHtml; }
    public function setMaterialsHtml(?string $materialsHtml): static { $this->materialsHtml = $materialsHtml; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, \App\Entity\VideoMaterial> */
    public function getMaterials(): Collection { return $this->materials; }
}
