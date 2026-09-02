<?php

namespace App\Entity;

use App\Repository\BibliaBookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BibliaBookRepository::class)]
#[ORM\Table(name: 'biblia_book')]
class BibliaBook
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BibliaTestament::class, inversedBy: 'bibliaBooks')]
    #[ORM\JoinColumn(name: 'testment_id', referencedColumnName: 'id', nullable: false)]
    private ?BibliaTestament $testment = null;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $abbreviation = '';

    #[ORM\Column(name: 'bible_com_abreviation', length: 255, nullable: true)]
    private ?string $bibleComAbreviation = null;

    #[ORM\Column(name: 'human_long', length: 255, nullable: true)]
    private ?string $humanLong = null;

    /** @var Collection<int, BibliaVerse> */
    #[ORM\OneToMany(mappedBy: 'book', targetEntity: BibliaVerse::class)]
    private Collection $bibliaVerses;

    /** @var Collection<int, BibliaVerseExt> */
    #[ORM\OneToMany(mappedBy: 'book', targetEntity: BibliaVerseExt::class)]
    private Collection $bibliaVerseExts;

    public function __construct()
    {
        $this->bibliaVerses = new ArrayCollection();
        $this->bibliaVerseExts = new ArrayCollection();
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

    public function getTestment(): ?BibliaTestament
    {
        return $this->testment;
    }

    public function setTestment(?BibliaTestament $testment): static
    {
        $this->testment = $testment;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): static
    {
        $this->abbreviation = $abbreviation;
        return $this;
    }

    public function getBibleComAbreviation(): ?string
    {
        return $this->bibleComAbreviation;
    }

    public function setBibleComAbreviation(?string $bibleComAbreviation): static
    {
        $this->bibleComAbreviation = $bibleComAbreviation;
        return $this;
    }

    public function getHumanLong(): ?string
    {
        return $this->humanLong;
    }

    public function setHumanLong(?string $humanLong): static
    {
        $this->humanLong = $humanLong;
        return $this;
    }

    /**
     * @return Collection<int, BibliaVerse>
     */
    public function getBibliaVerses(): Collection
    {
        return $this->bibliaVerses;
    }

    /**
     * @return Collection<int, BibliaVerseExt>
     */
    public function getBibliaVerseExts(): Collection
    {
        return $this->bibliaVerseExts;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
