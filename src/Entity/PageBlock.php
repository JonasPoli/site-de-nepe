<?php

namespace App\Entity;

use App\Repository\PageBlockRepository;
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[Vich\UploadableField(mapping: 'page_block_image', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSection(): ?PageSection { return $this->section; }
    public function setSection(?PageSection $section): static { $this->section = $section; return $this; }

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

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
