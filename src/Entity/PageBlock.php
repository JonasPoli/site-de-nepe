<?php

namespace App\Entity;

use App\Entity\Enum\BlockType;
use App\Repository\PageBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: PageBlockRepository::class)]
#[Vich\Uploadable]
class PageBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageSection::class, inversedBy: 'blocks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PageSection $section = null;

    /** Type of block — drives which fields/template are used */
    #[ORM\Column(length: 50, options: ['default' => 'text_image'])]
    private string $type = 'text_image';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    /** Main image (used by text_image type) */
    #[Vich\UploadableField(mapping: 'page_block_image', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** JSON config — stores blurbs, stats items, newsletter blorbs, banner slides, etc. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    /** Google Maps or other embed URL (map type) */
    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $embedUrl = null;

    /** Number of items to display (news_call, page_list) */
    #[ORM\Column(nullable: true)]
    private ?int $itemCount = null;

    /** Category to use as source (sub_categories, page_list) */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $relatedCategory = null;

    /** Images for gallery type */
    #[ORM\OneToMany(mappedBy: 'block', targetEntity: PageBlockImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $galleryImages;

    /** Testimonial items */
    #[ORM\OneToMany(mappedBy: 'block', targetEntity: PageBlockTestimonial::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $testimonials;

    /** Partner logo items */
    #[ORM\OneToMany(mappedBy: 'block', targetEntity: PageBlockPartnerLogo::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $partnerLogos;

    /** Team member items */
    #[ORM\OneToMany(mappedBy: 'block', targetEntity: PageBlockTeamMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $teamMembers;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt     = new \DateTimeImmutable();
        $this->galleryImages = new ArrayCollection();
        $this->testimonials  = new ArrayCollection();
        $this->partnerLogos  = new ArrayCollection();
        $this->teamMembers   = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSection(): ?PageSection { return $this->section; }
    public function setSection(?PageSection $section): static { $this->section = $section; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getBlockType(): BlockType { return BlockType::from($this->type); }

    public function getPreTitle(): ?string { return $this->preTitle; }
    public function setPreTitle(?string $preTitle): static { $this->preTitle = $preTitle; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }

    public function getText(): ?string { return $this->text; }
    public function setText(?string $text): static { $this->text = $text; return $this; }

    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;
        if ($imageFile) { $this->updatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getConfig(): ?array { return $this->config; }
    public function setConfig(?array $config): static { $this->config = $config; return $this; }

    public function getEmbedUrl(): ?string { return $this->embedUrl; }
    public function setEmbedUrl(?string $embedUrl): static { $this->embedUrl = $embedUrl; return $this; }

    public function getItemCount(): ?int { return $this->itemCount; }
    public function setItemCount(?int $itemCount): static { $this->itemCount = $itemCount; return $this; }

    public function getRelatedCategory(): ?Category { return $this->relatedCategory; }
    public function setRelatedCategory(?Category $category): static { $this->relatedCategory = $category; return $this; }

    /** @return Collection<int, PageBlockImage> */
    public function getGalleryImages(): Collection { return $this->galleryImages; }

    /** @return Collection<int, PageBlockTestimonial> */
    public function getTestimonials(): Collection { return $this->testimonials; }

    /** @return Collection<int, PageBlockPartnerLogo> */
    public function getPartnerLogos(): Collection { return $this->partnerLogos; }

    /** @return Collection<int, PageBlockTeamMember> */
    public function getTeamMembers(): Collection { return $this->teamMembers; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function __clone()
    {
        $this->id           = null;
        $this->galleryImages = new ArrayCollection();
        $this->testimonials  = new ArrayCollection();
        $this->partnerLogos  = new ArrayCollection();
        $this->teamMembers   = new ArrayCollection();
    }
}
