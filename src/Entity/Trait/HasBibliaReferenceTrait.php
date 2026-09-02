<?php

namespace App\Entity\Trait;

use App\Entity\BibliaBook;
use Doctrine\ORM\Mapping as ORM;

trait HasBibliaReferenceTrait
{
    #[ORM\ManyToOne(targetEntity: BibliaBook::class)]
    #[ORM\JoinColumn(name: 'biblia_book_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?BibliaBook $bibliaBook = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bibliaChapter = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bibliaVerseStart = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bibliaVerseEnd = null;

    public function getBibliaBook(): ?BibliaBook
    {
        return $this->bibliaBook;
    }

    public function setBibliaBook(?BibliaBook $bibliaBook): static
    {
        $this->bibliaBook = $bibliaBook;
        return $this;
    }

    public function getBibliaChapter(): ?int
    {
        return $this->bibliaChapter;
    }

    public function setBibliaChapter(?int $bibliaChapter): static
    {
        $this->bibliaChapter = $bibliaChapter;
        return $this;
    }

    public function getBibliaVerseStart(): ?int
    {
        return $this->bibliaVerseStart;
    }

    public function setBibliaVerseStart(?int $bibliaVerseStart): static
    {
        $this->bibliaVerseStart = $bibliaVerseStart;
        return $this;
    }

    public function getBibliaVerseEnd(): ?int
    {
        return $this->bibliaVerseEnd;
    }

    public function setBibliaVerseEnd(?int $bibliaVerseEnd): static
    {
        $this->bibliaVerseEnd = $bibliaVerseEnd;
        return $this;
    }

    public function hasBibliaReference(): bool
    {
        return $this->bibliaBook !== null && $this->bibliaChapter !== null && $this->bibliaVerseStart !== null;
    }

    public function getBibliaReferenceFormatted(): ?string
    {
        if (!$this->hasBibliaReference()) {
            return null;
        }

        $bookName = $this->bibliaBook?->getName() ?? '';
        if ($this->bibliaVerseEnd && $this->bibliaVerseEnd > $this->bibliaVerseStart) {
            return sprintf('%s %d:%d-%d', $bookName, $this->bibliaChapter, $this->bibliaVerseStart, $this->bibliaVerseEnd);
        }

        return sprintf('%s %d:%d', $bookName, $this->bibliaChapter, $this->bibliaVerseStart);
    }
}
