<?php

namespace App\Entity;

use App\Repository\PageBlockPartnerLogoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: PageBlockPartnerLogoRepository::class)]
#[Vich\Uploadable]
class PageBlockPartnerLogo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PageBlock::class, inversedBy: 'partnerLogos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PageBlock $block = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[Vich\UploadableField(mapping: 'partner_logo', fileNameProperty: 'logoFilename')]
    private ?File $logoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoFilename = null;

    /** External link (optional) */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getBlock(): ?PageBlock { return $this->block; }
    public function setBlock(?PageBlock $block): static { $this->block = $block; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getLogoFile(): ?File { return $this->logoFile; }
    public function setLogoFile(?File $file): static
    {
        $this->logoFile = $file;
        if ($file) { $this->updatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getLogoFilename(): ?string { return $this->logoFilename; }
    public function setLogoFilename(?string $logoFilename): static { $this->logoFilename = $logoFilename; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): static { $this->url = $url; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
