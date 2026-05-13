<?php

namespace App\Entity;

use App\Repository\EventoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: EventoRepository::class)]
#[Vich\Uploadable]
class Evento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nome = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descricao = null;

    #[ORM\Column(length: 255)]
    private ?string $chavePix = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $beneficiarioPix = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cidadePix = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mensagemSucesso = null;

    // Banner
    #[Vich\UploadableField(mapping: 'evento_banner', fileNameProperty: 'bannerName')]
    private ?File $bannerFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bannerName = null;

    // Logo
    #[Vich\UploadableField(mapping: 'evento_logo', fileNameProperty: 'logoName')]
    private ?File $logoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dataInicio = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dataFim = null;

    #[ORM\Column(length: 20, options: ['default' => 'ativo'])]
    private ?string $status = 'ativo';

    #[ORM\Column(length: 100, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 20, nullable: true, options: ['default' => '#ffffff'])]
    private ?string $corBackground = '#ffffff';

    #[ORM\Column(length: 20, nullable: true, options: ['default' => '#000000'])]
    private ?string $corTexto = '#000000';

    #[ORM\Column(length: 20, nullable: true, options: ['default' => '#333333'])]
    private ?string $corTextoSecundario = '#333333';

    #[ORM\Column(length: 20, nullable: true, options: ['default' => '#007bff'])]
    private ?string $corBotaoPrimario = '#007bff';

    #[ORM\Column(length: 20, nullable: true, options: ['default' => '#6c757d'])]
    private ?string $corBotaoSecundario = '#6c757d';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, TipoInscricao>
     */
    #[ORM\OneToMany(targetEntity: TipoInscricao::class, mappedBy: 'evento', cascade: ['persist', 'remove'])]
    private Collection $tiposInscricao;

    /**
     * @var Collection<int, Inscricao>
     */
    #[ORM\OneToMany(targetEntity: Inscricao::class, mappedBy: 'evento', cascade: ['persist', 'remove'])]
    private Collection $inscricoes;

    public function __construct()
    {
        $this->tiposInscricao = new ArrayCollection();
        $this->inscricoes = new ArrayCollection();
        $this->token = uniqid('', true) . uniqid('', true);
    }

    public function getId(): ?int { return $this->id; }

    public function getNome(): ?string { return $this->nome; }
    public function setNome(string $nome): static { $this->nome = $nome; return $this; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): static { $this->descricao = $descricao; return $this; }

    public function getChavePix(): ?string { return $this->chavePix; }
    public function setChavePix(string $chavePix): static { $this->chavePix = $chavePix; return $this; }

    public function getBeneficiarioPix(): ?string { return $this->beneficiarioPix; }
    public function setBeneficiarioPix(?string $beneficiarioPix): static { $this->beneficiarioPix = $beneficiarioPix; return $this; }

    public function getCidadePix(): ?string { return $this->cidadePix; }
    public function setCidadePix(?string $cidadePix): static { $this->cidadePix = $cidadePix; return $this; }

    public function getMensagemSucesso(): ?string { return $this->mensagemSucesso; }
    public function setMensagemSucesso(?string $mensagemSucesso): static { $this->mensagemSucesso = $mensagemSucesso; return $this; }

    public function getBannerFile(): ?File { return $this->bannerFile; }
    public function setBannerFile(?File $bannerFile = null): void
    {
        $this->bannerFile = $bannerFile;
        if (null !== $bannerFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getBannerName(): ?string { return $this->bannerName; }
    public function setBannerName(?string $bannerName): void { $this->bannerName = $bannerName; }

    public function getLogoFile(): ?File { return $this->logoFile; }
    public function setLogoFile(?File $logoFile = null): void
    {
        $this->logoFile = $logoFile;
        if (null !== $logoFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getLogoName(): ?string { return $this->logoName; }
    public function setLogoName(?string $logoName): void { $this->logoName = $logoName; }

    public function getDataInicio(): ?\DateTimeInterface { return $this->dataInicio; }
    public function setDataInicio(?\DateTimeInterface $dataInicio): static { $this->dataInicio = $dataInicio; return $this; }

    public function getDataFim(): ?\DateTimeInterface { return $this->dataFim; }
    public function setDataFim(?\DateTimeInterface $dataFim): static { $this->dataFim = $dataFim; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(string $token): static { $this->token = $token; return $this; }

    public function getCorBackground(): ?string { return $this->corBackground; }
    public function setCorBackground(?string $corBackground): static { $this->corBackground = $corBackground; return $this; }

    public function getCorTexto(): ?string { return $this->corTexto; }
    public function setCorTexto(?string $corTexto): static { $this->corTexto = $corTexto; return $this; }

    public function getCorTextoSecundario(): ?string { return $this->corTextoSecundario; }
    public function setCorTextoSecundario(?string $corTextoSecundario): static { $this->corTextoSecundario = $corTextoSecundario; return $this; }

    public function getCorBotaoPrimario(): ?string { return $this->corBotaoPrimario; }
    public function setCorBotaoPrimario(?string $corBotaoPrimario): static { $this->corBotaoPrimario = $corBotaoPrimario; return $this; }

    public function getCorBotaoSecundario(): ?string { return $this->corBotaoSecundario; }
    public function setCorBotaoSecundario(?string $corBotaoSecundario): static { $this->corBotaoSecundario = $corBotaoSecundario; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /** @return Collection<int, TipoInscricao> */
    public function getTiposInscricao(): Collection { return $this->tiposInscricao; }

    public function addTiposInscricao(TipoInscricao $tipo): static
    {
        if (!$this->tiposInscricao->contains($tipo)) {
            $this->tiposInscricao->add($tipo);
            $tipo->setEvento($this);
        }
        return $this;
    }

    public function removeTiposInscricao(TipoInscricao $tipo): static
    {
        if ($this->tiposInscricao->removeElement($tipo)) {
            if ($tipo->getEvento() === $this) {
                $tipo->setEvento(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Inscricao> */
    public function getInscricoes(): Collection { return $this->inscricoes; }

    public function addInscricao(Inscricao $inscricao): static
    {
        if (!$this->inscricoes->contains($inscricao)) {
            $this->inscricoes->add($inscricao);
            $inscricao->setEvento($this);
        }
        return $this;
    }

    public function removeInscricao(Inscricao $inscricao): static
    {
        if ($this->inscricoes->removeElement($inscricao)) {
            if ($inscricao->getEvento() === $this) {
                $inscricao->setEvento(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->nome ?? '';
    }
}
