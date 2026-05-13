<?php

namespace App\Entity;

use App\Repository\PageSectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageSectionRepository::class)]
class PageSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Page $page = null;

    /** Displayed on secondary-color background */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titlePart1 = null;

    /** Displayed on primary-color background */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titlePart2 = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /** @var Collection<int, PageBlock> */
    #[ORM\OneToMany(mappedBy: 'section', targetEntity: PageBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getPage(): ?Page { return $this->page; }
    public function setPage(?Page $page): static { $this->page = $page; return $this; }

    public function getTitlePart1(): ?string { return $this->titlePart1; }
    public function setTitlePart1(?string $titlePart1): static { $this->titlePart1 = $titlePart1; return $this; }

    public function getTitlePart2(): ?string { return $this->titlePart2; }
    public function setTitlePart2(?string $titlePart2): static { $this->titlePart2 = $titlePart2; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    /** @return Collection<int, PageBlock> */
    public function getBlocks(): Collection { return $this->blocks; }

    public function __toString(): string
    {
        return trim(($this->titlePart1 ?? '') . ' ' . ($this->titlePart2 ?? '')) ?: 'Seção #' . $this->id;
    }
}
