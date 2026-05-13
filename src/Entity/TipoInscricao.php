<?php

namespace App\Entity;

use App\Repository\TipoInscricaoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TipoInscricaoRepository::class)]
class TipoInscricao
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nome = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $valorBase = null;

    #[ORM\Column(length: 20, options: ['default' => 'ativo'])]
    private ?string $status = 'ativo';

    #[ORM\ManyToOne(inversedBy: 'tiposInscricao')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evento $evento = null;

    /**
     * @var Collection<int, ItemAdicional>
     */
    #[ORM\OneToMany(targetEntity: ItemAdicional::class, mappedBy: 'tipoInscricao', cascade: ['persist', 'remove'])]
    private Collection $itensAdicionais;

    /**
     * @var Collection<int, Inscrito>
     */
    #[ORM\OneToMany(targetEntity: Inscrito::class, mappedBy: 'tipoInscricao')]
    private Collection $inscritos;

    public function __construct()
    {
        $this->itensAdicionais = new ArrayCollection();
        $this->inscritos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getValorBase(): ?string { return $this->valorBase; }
    public function setValorBase(string $valorBase): static { $this->valorBase = $valorBase; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getEvento(): ?Evento { return $this->evento; }
    public function setEvento(?Evento $evento): static { $this->evento = $evento; return $this; }

    /** @return Collection<int, ItemAdicional> */
    public function getItensAdicionais(): Collection { return $this->itensAdicionais; }

    public function addItensAdicionais(ItemAdicional $item): static
    {
        if (!$this->itensAdicionais->contains($item)) {
            $this->itensAdicionais->add($item);
            $item->setTipoInscricao($this);
        }
        return $this;
    }

    public function removeItensAdicionais(ItemAdicional $item): static
    {
        if ($this->itensAdicionais->removeElement($item)) {
            if ($item->getTipoInscricao() === $this) {
                $item->setTipoInscricao(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Inscrito> */
    public function getInscritos(): Collection { return $this->inscritos; }

    public function __toString(): string
    {
        return $this->nome ?? '';
    }
}
