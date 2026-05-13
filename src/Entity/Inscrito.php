<?php

namespace App\Entity;

use App\Repository\InscritoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InscritoRepository::class)]
class Inscrito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomeCompleto = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomeCracha = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dataNascimento = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $cpf = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $whatsapp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomeContatoEmergencia = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefoneContatoEmergencia = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cidade = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $estado = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $restricaoAlimentar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $alergia = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $aceiteLgpd = false;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $aceiteImagem = false;

    #[ORM\Column(length: 20, options: ['default' => 'ativo'])]
    private ?string $status = 'ativo';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'inscritos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Inscricao $inscricao = null;

    #[ORM\ManyToOne(inversedBy: 'inscritos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TipoInscricao $tipoInscricao = null;

    /**
     * @var Collection<int, ItemAdicional>
     */
    #[ORM\ManyToMany(targetEntity: ItemAdicional::class, inversedBy: 'inscritos')]
    private Collection $itensAdicionais;

    public function __construct()
    {
        $this->itensAdicionais = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNomeCompleto(): ?string { return $this->nomeCompleto; }
    public function setNomeCompleto(string $nomeCompleto): static { $this->nomeCompleto = $nomeCompleto; return $this; }

    public function getNomeCracha(): ?string { return $this->nomeCracha; }
    public function setNomeCracha(?string $nomeCracha): static { $this->nomeCracha = $nomeCracha; return $this; }

    public function getDataNascimento(): ?\DateTimeInterface { return $this->dataNascimento; }
    public function setDataNascimento(?\DateTimeInterface $dataNascimento): static { $this->dataNascimento = $dataNascimento; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getCpf(): ?string { return $this->cpf; }
    public function setCpf(?string $cpf): static { $this->cpf = $cpf; return $this; }

    public function getWhatsapp(): ?string { return $this->whatsapp; }
    public function setWhatsapp(?string $whatsapp): static { $this->whatsapp = $whatsapp; return $this; }

    public function getNomeContatoEmergencia(): ?string { return $this->nomeContatoEmergencia; }
    public function setNomeContatoEmergencia(?string $nomeContatoEmergencia): static { $this->nomeContatoEmergencia = $nomeContatoEmergencia; return $this; }

    public function getTelefoneContatoEmergencia(): ?string { return $this->telefoneContatoEmergencia; }
    public function setTelefoneContatoEmergencia(?string $telefoneContatoEmergencia): static { $this->telefoneContatoEmergencia = $telefoneContatoEmergencia; return $this; }

    public function getCidade(): ?string { return $this->cidade; }
    public function setCidade(?string $cidade): static { $this->cidade = $cidade; return $this; }

    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(?string $estado): static { $this->estado = $estado; return $this; }

    public function getRestricaoAlimentar(): ?string { return $this->restricaoAlimentar; }
    public function setRestricaoAlimentar(?string $restricaoAlimentar): static { $this->restricaoAlimentar = $restricaoAlimentar; return $this; }

    public function getAlergia(): ?string { return $this->alergia; }
    public function setAlergia(?string $alergia): static { $this->alergia = $alergia; return $this; }

    public function isAceiteLgpd(): ?bool { return $this->aceiteLgpd; }
    public function setAceiteLgpd(bool $aceiteLgpd): static { $this->aceiteLgpd = $aceiteLgpd; return $this; }

    public function isAceiteImagem(): ?bool { return $this->aceiteImagem; }
    public function setAceiteImagem(bool $aceiteImagem): static { $this->aceiteImagem = $aceiteImagem; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getInscricao(): ?Inscricao { return $this->inscricao; }
    public function setInscricao(?Inscricao $inscricao): static { $this->inscricao = $inscricao; return $this; }

    public function getTipoInscricao(): ?TipoInscricao { return $this->tipoInscricao; }
    public function setTipoInscricao(?TipoInscricao $tipoInscricao): static { $this->tipoInscricao = $tipoInscricao; return $this; }

    /** @return Collection<int, ItemAdicional> */
    public function getItensAdicionais(): Collection { return $this->itensAdicionais; }

    public function addItensAdicionais(ItemAdicional $item): static
    {
        if (!$this->itensAdicionais->contains($item)) {
            $this->itensAdicionais->add($item);
        }
        return $this;
    }

    public function removeItensAdicionais(ItemAdicional $item): static
    {
        $this->itensAdicionais->removeElement($item);
        return $this;
    }

    public function getValorTotal(): float
    {
        $total = (float) ($this->tipoInscricao?->getValorBase() ?? 0);
        foreach ($this->itensAdicionais as $item) {
            $total += (float) $item->getValor();
        }
        return $total;
    }
}
