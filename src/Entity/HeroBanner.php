<?php

namespace App\Entity;

use App\Contract\TenantAwareInterface;
use App\Repository\HeroBannerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: HeroBannerRepository::class)]
#[Vich\Uploadable]
class HeroBanner implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ctaText = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ctaLink = null;

    #[Vich\UploadableField(mapping: 'hero_banner', fileNameProperty: 'backgroundImage')]
    private ?File $backgroundImageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backgroundImage = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getSubtitle(): ?string { return $this->subtitle; }
    public function setSubtitle(?string $subtitle): static { $this->subtitle = $subtitle; return $this; }

    public function getCtaText(): ?string { return $this->ctaText; }
    public function setCtaText(?string $ctaText): static { $this->ctaText = $ctaText; return $this; }

    public function getCtaLink(): ?string { return $this->ctaLink; }
    public function setCtaLink(?string $ctaLink): static { $this->ctaLink = $ctaLink; return $this; }

    public function getBackgroundImageFile(): ?File { return $this->backgroundImageFile; }
    public function setBackgroundImageFile(?File $file): static
    {
        $this->backgroundImageFile = $file;
        if ($file) { $this->updatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getBackgroundImage(): ?string { return $this->backgroundImage; }
    public function setBackgroundImage(?string $backgroundImage): static { $this->backgroundImage = $backgroundImage; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
