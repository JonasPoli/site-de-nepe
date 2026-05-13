<?php

namespace App\Entity;

use App\Repository\ItemAdicionalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemAdicionalRepository::class)]
class ItemAdicional
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $descricao = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $valor = null;

    #[ORM\Column(length: 20, options: ['default' => 'ativo'])]
    private ?string $status = 'ativo';

    #[ORM\ManyToOne(inversedBy: 'itensAdicionais')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TipoInscricao $tipoInscricao = null;

    /**
     * @var Collection<int, Inscrito>
     */
    #[ORM\ManyToMany(targetEntity: Inscrito::class, mappedBy: 'itensAdicionais')]
    private Collection $inscritos;

    public function __construct()
    {
        $this->inscritos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getValor(): ?string { return $this->valor; }
    public function setValor(string $valor): static { $this->valor = $valor; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getTipoInscricao(): ?TipoInscricao { return $this->tipoInscricao; }
    public function setTipoInscricao(?TipoInscricao $tipoInscricao): static { $this->tipoInscricao = $tipoInscricao; return $this; }

    /** @return Collection<int, Inscrito> */
    public function getInscritos(): Collection { return $this->inscritos; }

    public function __toString(): string
    {
        return $this->descricao ?? '';
    }
}
