<?php

namespace App\Entity;

use App\Repository\ArticleImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ArticleImageRepository::class)]
#[Vich\Uploadable]
class ArticleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'gallery')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[Vich\UploadableField(mapping: 'article_gallery', fileNameProperty: 'filename')]
    private ?File $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct() { $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $article): static { $this->article = $article; return $this; }
    public function getFile(): ?File { return $this->file; }
    public function setFile(?File $file): static { $this->file = $file; if (null !== $file) { $this->updatedAt = new \DateTimeImmutable(); } return $this; }
    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(?string $filename): static { $this->filename = $filename; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
