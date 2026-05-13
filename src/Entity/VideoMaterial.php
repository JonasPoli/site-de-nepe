<?php

namespace App\Entity;

use App\Repository\VideoMaterialRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: VideoMaterialRepository::class)]
#[Vich\Uploadable]
class VideoMaterial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VideoSupport::class, inversedBy: 'materials')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?VideoSupport $video = null;

    /** Label shown to the user (e.g. "Slides da Aula 1") */
    #[ORM\Column(length: 255)]
    private string $label = '';

    #[Vich\UploadableField(mapping: 'video_material', fileNameProperty: 'filename')]
    private ?File $file = null;

    #[ORM\Column(length: 255)]
    private string $filename = '';

    /** Original extension to pick the right icon on the front-end */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getVideo(): ?VideoSupport { return $this->video; }
    public function setVideo(?VideoSupport $video): static { $this->video = $video; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): static { $this->label = $label; return $this; }

    public function getFile(): ?File { return $this->file; }
    public function setFile(?File $file): static
    {
        $this->file = $file;
        if ($file) { $this->updatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $filename): static { $this->filename = $filename; return $this; }

    public function getExtension(): ?string { return $this->extension; }
    public function setExtension(?string $extension): static { $this->extension = $extension; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}
