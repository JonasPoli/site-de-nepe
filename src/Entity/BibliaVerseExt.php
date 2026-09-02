<?php

namespace App\Entity;

use App\Repository\BibliaVerseExtRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BibliaVerseExtRepository::class)]
#[ORM\Table(name: 'biblia_verse_ext')]
#[ORM\Index(name: 'verse_ext_book_ch_v_idx', columns: ['book_id', 'chapter', 'verse'])]
class BibliaVerseExt
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BibliaBook::class, inversedBy: 'bibliaVerseExts')]
    #[ORM\JoinColumn(name: 'book_id', referencedColumnName: 'id', nullable: false)]
    private ?BibliaBook $book = null;

    #[ORM\Column(type: 'integer')]
    private int $chapter = 0;

    #[ORM\Column(type: 'integer')]
    private int $verse = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $year = null;

    #[ORM\Column(name: 'year_description', length: 255, nullable: true)]
    private ?string $yearDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $place = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $translated = 0;

    /** @var Collection<int, BibliaVerse> */
    #[ORM\OneToMany(mappedBy: 'external_id', targetEntity: BibliaVerse::class)]
    private Collection $bibliaVerses;

    public function __construct()
    {
        $this->bibliaVerses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
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

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getYearDescription(): ?string
    {
        return $this->yearDescription;
    }

    public function setYearDescription(?string $yearDescription): static
    {
        $this->yearDescription = $yearDescription;
        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(?string $place): static
    {
        $this->place = $place;
        return $this;
    }

    public function getTranslated(): int
    {
        return $this->translated;
    }

    public function setTranslated(int $translated): static
    {
        $this->translated = $translated;
        return $this;
    }

    /**
     * @return Collection<int, BibliaVerse>
     */
    public function getBibliaVerses(): Collection
    {
        return $this->bibliaVerses;
    }
}
