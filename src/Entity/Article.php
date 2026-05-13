<?php

namespace App\Entity;

use App\Contract\TenantAwareInterface;
use App\Entity\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[Vich\Uploadable]
class Article implements TenantAwareInterface
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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[Vich\UploadableField(mapping: 'article_image', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageAlt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isNoIndex = false;

    #[ORM\Column(length: 20, enumType: ArticleStatus::class)]
    private ArticleStatus $status = ArticleStatus::Draft;

    /** @var Collection<int, ArticleApproval> */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleApproval::class, cascade: ['persist', 'remove'])]
    private Collection $approvals;

    /** @var Collection<int, ArticleImage> */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $gallery;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->approvals = new ArrayCollection();
        $this->gallery = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getShortDescription(): ?string { return $this->shortDescription; }
    public function setShortDescription(?string $shortDescription): static { $this->shortDescription = $shortDescription; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): static { $this->content = $content; return $this; }

    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;
        if ($imageFile) { $this->updatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getImageAlt(): ?string { return $this->imageAlt; }
    public function setImageAlt(?string $imageAlt): static { $this->imageAlt = $imageAlt; return $this; }

    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }

    public function getSeoTitle(): ?string { return $this->seoTitle; }
    public function setSeoTitle(?string $seoTitle): static { $this->seoTitle = $seoTitle; return $this; }

    public function getSeoDescription(): ?string { return $this->seoDescription; }
    public function setSeoDescription(?string $seoDescription): static { $this->seoDescription = $seoDescription; return $this; }

    public function getCanonicalUrl(): ?string { return $this->canonicalUrl; }
    public function setCanonicalUrl(?string $canonicalUrl): static { $this->canonicalUrl = $canonicalUrl; return $this; }

    public function isNoIndex(): bool { return $this->isNoIndex; }
    public function setIsNoIndex(bool $isNoIndex): static { $this->isNoIndex = $isNoIndex; return $this; }

    public function getStatus(): ArticleStatus { return $this->status; }
    public function setStatus(ArticleStatus $status): static { $this->status = $status; return $this; }

    /** @return Collection<int, ArticleApproval> */
    public function getApprovals(): Collection { return $this->approvals; }

    public function getApprovalCount(): int { return $this->approvals->count(); }

    public function isApprovedBy(User $user): bool
    {
        foreach ($this->approvals as $approval) {
            if ($approval->getReviewer() === $user) {
                return true;
            }
        }
        return false;
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /** @return Collection<int, ArticleImage> */
    public function getGallery(): Collection { return $this->gallery; }
}
