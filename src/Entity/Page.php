<?php

namespace App\Entity;

use App\Contract\TenantAwareInterface;
use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageRepository::class)]
class Page implements TenantAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $showInHeader = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $showInFooter = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoDescription = null;

    /** @var Collection<int, PageSection> */
    #[ORM\OneToMany(mappedBy: 'page', targetEntity: PageSection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sections;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function isShowInHeader(): bool { return $this->showInHeader; }
    public function setShowInHeader(bool $showInHeader): static { $this->showInHeader = $showInHeader; return $this; }

    public function isShowInFooter(): bool { return $this->showInFooter; }
    public function setShowInFooter(bool $showInFooter): static { $this->showInFooter = $showInFooter; return $this; }

    public function getSeoTitle(): ?string { return $this->seoTitle; }
    public function setSeoTitle(?string $seoTitle): static { $this->seoTitle = $seoTitle; return $this; }

    public function getSeoDescription(): ?string { return $this->seoDescription; }
    public function setSeoDescription(?string $seoDescription): static { $this->seoDescription = $seoDescription; return $this; }

    /** @return Collection<int, PageSection> */
    public function getSections(): Collection { return $this->sections; }

    public function __toString(): string { return $this->title; }
}
