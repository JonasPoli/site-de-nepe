<?php

namespace App\Entity;

use App\Repository\BibliaVersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BibliaVersionRepository::class)]
#[ORM\Table(name: 'biblia_version')]
class BibliaVersion
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'bible_com_abreviation', length: 255, nullable: true)]
    private ?string $bibleComAbreviation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $abbreviation = null;

    /** @var Collection<int, BibliaVerse> */
    #[ORM\OneToMany(mappedBy: 'version', targetEntity: BibliaVerse::class)]
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getAbbreviation(): ?string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(?string $abbreviation): static
    {
        $this->abbreviation = $abbreviation;
        return $this;
    }

    /**
     * @return Collection<int, BibliaVerse>
     */
    public function getBibliaVerses(): Collection
    {
        return $this->bibliaVerses;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
