<?php

namespace App\Entity;

use App\Repository\InscricaoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InscricaoRepository::class)]
class Inscricao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomeCadastrante = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'inscricoes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evento $evento = null;

    /**
     * @var Collection<int, Inscrito>
     */
    #[ORM\OneToMany(targetEntity: Inscrito::class, mappedBy: 'inscricao', cascade: ['persist', 'remove'])]
    private Collection $inscritos;

    public function __construct()
    {
        $this->inscritos = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNomeCadastrante(): ?string { return $this->nomeCadastrante; }
    public function setNomeCadastrante(string $nomeCadastrante): static { $this->nomeCadastrante = $nomeCadastrante; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getEvento(): ?Evento { return $this->evento; }
    public function setEvento(?Evento $evento): static { $this->evento = $evento; return $this; }

    /** @return Collection<int, Inscrito> */
    public function getInscritos(): Collection { return $this->inscritos; }

    public function addInscrito(Inscrito $inscrito): static
    {
        if (!$this->inscritos->contains($inscrito)) {
            $this->inscritos->add($inscrito);
            $inscrito->setInscricao($this);
        }
        return $this;
    }

    public function removeInscrito(Inscrito $inscrito): static
    {
        if ($this->inscritos->removeElement($inscrito)) {
            if ($inscrito->getInscricao() === $this) {
                $inscrito->setInscricao(null);
            }
        }
        return $this;
    }

    public function getValorTotal(): float
    {
        $total = 0;
        foreach ($this->inscritos as $inscrito) {
            $total += $inscrito->getValorTotal();
        }
        return $total;
    }
}
