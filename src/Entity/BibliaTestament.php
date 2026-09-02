<?php

namespace App\Entity;

use App\Repository\BibliaTestamentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BibliaTestamentRepository::class)]
#[ORM\Table(name: 'biblia_testament')]
class BibliaTestament
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var Collection<int, BibliaBook> */
    #[ORM\OneToMany(mappedBy: 'testment', targetEntity: BibliaBook::class)]
    private Collection $bibliaBooks;

    public function __construct()
    {
        $this->bibliaBooks = new ArrayCollection();
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

    /**
     * @return Collection<int, BibliaBook>
     */
    public function getBibliaBooks(): Collection
    {
        return $this->bibliaBooks;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
