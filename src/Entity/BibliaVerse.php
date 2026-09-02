<?php

namespace App\Entity;

use App\Repository\BibliaVerseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BibliaVerseRepository::class)]
#[ORM\Table(name: 'biblia_verse')]
#[ORM\Index(name: 'verse_lookup_idx', columns: ['version_id', 'book_id', 'chapter', 'verse'])]
class BibliaVerse
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BibliaVersion::class, inversedBy: 'bibliaVerses')]
    #[ORM\JoinColumn(name: 'version_id', referencedColumnName: 'id', nullable: false)]
    private ?BibliaVersion $version = null;

    #[ORM\ManyToOne(targetEntity: BibliaBook::class, inversedBy: 'bibliaVerses')]
    #[ORM\JoinColumn(name: 'book_id', referencedColumnName: 'id', nullable: false)]
    private ?BibliaBook $book = null;

    #[ORM\Column(type: 'integer')]
    private int $chapter = 0;

    #[ORM\Column(type: 'integer')]
    private int $verse = 0;

    #[ORM\Column(type: Types::TEXT)]
    private string $text = '';

    #[ORM\ManyToOne(targetEntity: BibliaVerseExt::class, inversedBy: 'bibliaVerses')]
    #[ORM\JoinColumn(name: 'external_id_id', referencedColumnName: 'id', nullable: true)]
    private ?BibliaVerseExt $external_id = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $subject = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getVersion(): ?BibliaVersion
    {
        return $this->version;
    }

    public function setVersion(?BibliaVersion $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getBook(): ?BibliaBook
    {
        return $this->book;
    }

    public function setBook(?BibliaBook $book): static
    {
        $this->book = $book;
        return $this;
    }

    public function getChapter(): int
    {
        return $this->chapter;
    }

    public function setChapter(int $chapter): static
    {
        $this->chapter = $chapter;
        return $this;
    }

    public function getVerse(): int
    {
        return $this->verse;
    }

    public function setVerse(int $verse): static
    {
        $this->verse = $verse;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getExternalId(): ?BibliaVerseExt
    {
        return $this->external_id;
    }

    public function setExternalId(?BibliaVerseExt $external_id): static
    {
        $this->external_id = $external_id;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %d:%d - %s', $this->book?->getName(), $this->chapter, $this->verse, mb_substr($this->text, 0, 50));
    }
}
