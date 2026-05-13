<?php

namespace App\Entity;

use App\Repository\TenantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[Vich\Uploadable]
class Tenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $domain = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[Vich\UploadableField(mapping: 'tenant_logo', fileNameProperty: 'logo')]
    private ?File $logoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $primaryColor = '#0044cc';

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $secondaryColor = '#ffaa00';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instagramLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $whatsappLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $linkedinLink = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aboutText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aboutFullText = null;

    #[Vich\UploadableField(mapping: 'tenant_about_image', fileNameProperty: 'aboutImage')]
    private ?File $aboutImageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $aboutImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $mapsEmbedUrl = null;

    /** Minimum approvals needed for an article to be published */
    #[ORM\Column(options: ['default' => 1])]
    private int $requiredApprovals = 1;

    /** Which visual theme this tenant uses: 'nepe' or 'moderno' */
    #[ORM\Column(length: 50, options: ['default' => 'nepe'])]
    private string $theme = 'nepe';

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDomain(): string { return $this->domain; }
    public function setDomain(string $domain): static { $this->domain = $domain; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getLogoFile(): ?File { return $this->logoFile; }
    public function setLogoFile(?File $logoFile): static
    {
        $this->logoFile = $logoFile;
        if ($logoFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $logo): static { $this->logo = $logo; return $this; }

    public function getPrimaryColor(): ?string { return $this->primaryColor; }
    public function setPrimaryColor(?string $primaryColor): static { $this->primaryColor = $primaryColor; return $this; }

    public function getSecondaryColor(): ?string { return $this->secondaryColor; }
    public function setSecondaryColor(?string $secondaryColor): static { $this->secondaryColor = $secondaryColor; return $this; }

    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $contactEmail): static { $this->contactEmail = $contactEmail; return $this; }

    public function getYoutubeLink(): ?string { return $this->youtubeLink; }
    public function setYoutubeLink(?string $youtubeLink): static { $this->youtubeLink = $youtubeLink; return $this; }

    public function getInstagramLink(): ?string { return $this->instagramLink; }
    public function setInstagramLink(?string $instagramLink): static { $this->instagramLink = $instagramLink; return $this; }

    public function getFacebookLink(): ?string { return $this->facebookLink; }
    public function setFacebookLink(?string $facebookLink): static { $this->facebookLink = $facebookLink; return $this; }

    public function getWhatsappLink(): ?string { return $this->whatsappLink; }
    public function setWhatsappLink(?string $whatsappLink): static { $this->whatsappLink = $whatsappLink; return $this; }

    public function getLinkedinLink(): ?string { return $this->linkedinLink; }
    public function setLinkedinLink(?string $linkedinLink): static { $this->linkedinLink = $linkedinLink; return $this; }

    public function getAboutText(): ?string { return $this->aboutText; }
    public function setAboutText(?string $aboutText): static { $this->aboutText = $aboutText; return $this; }

    public function getAboutFullText(): ?string { return $this->aboutFullText; }
    public function setAboutFullText(?string $t): static { $this->aboutFullText = $t; return $this; }

    public function getAboutImageFile(): ?File { return $this->aboutImageFile; }
    public function setAboutImageFile(?File $f): static
    {
        $this->aboutImageFile = $f;
        if ($f) { $this->updatedAt = new \DateTimeImmutable(); }
        return $this;
    }

    public function getAboutImage(): ?string { return $this->aboutImage; }
    public function setAboutImage(?string $aboutImage): static { $this->aboutImage = $aboutImage; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getMapsEmbedUrl(): ?string { return $this->mapsEmbedUrl; }
    public function setMapsEmbedUrl(?string $url): static { $this->mapsEmbedUrl = $url; return $this; }

    public function getRequiredApprovals(): int { return $this->requiredApprovals; }
    public function setRequiredApprovals(int $requiredApprovals): static { $this->requiredApprovals = $requiredApprovals; return $this; }

    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): static { $this->theme = $theme; return $this; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /**
     * Exclude $logoFile (Symfony\Component\HttpFoundation\File\File) from serialization.
     * VichUploader injects a File object when inject_on_load=true, but File objects
     * cannot be serialized — which causes session failures during _switch_user / impersonation.
     */
    public function __serialize(): array
    {
        return [
            'id'               => $this->id,
            'domain'           => $this->domain,
            'name'             => $this->name,
            'logo'             => $this->logo,
            'primaryColor'     => $this->primaryColor,
            'secondaryColor'   => $this->secondaryColor,
            'contactEmail'     => $this->contactEmail,
            'youtubeLink'      => $this->youtubeLink,
            'instagramLink'    => $this->instagramLink,
            'facebookLink'     => $this->facebookLink,
            'whatsappLink'     => $this->whatsappLink,
            'linkedinLink'     => $this->linkedinLink,
            'aboutText'        => $this->aboutText,
            'aboutFullText'    => $this->aboutFullText,
            'aboutImage'       => $this->aboutImage,
            'address'          => $this->address,
            'phone'            => $this->phone,
            'mapsEmbedUrl'     => $this->mapsEmbedUrl,
            'requiredApprovals'=> $this->requiredApprovals,
            'theme'            => $this->theme,
            'updatedAt'        => $this->updatedAt,
            // logoFile intentionally excluded — it is not serializable
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id                = $data['id'];
        $this->domain            = $data['domain'];
        $this->name              = $data['name'];
        $this->logo              = $data['logo'];
        $this->primaryColor      = $data['primaryColor'];
        $this->secondaryColor    = $data['secondaryColor'];
        $this->contactEmail      = $data['contactEmail'];
        $this->youtubeLink       = $data['youtubeLink'];
        $this->instagramLink     = $data['instagramLink'];
        $this->facebookLink      = $data['facebookLink'] ?? null;
        $this->whatsappLink      = $data['whatsappLink'] ?? null;
        $this->linkedinLink      = $data['linkedinLink'] ?? null;
        $this->aboutText         = $data['aboutText'] ?? null;
        $this->aboutFullText     = $data['aboutFullText'] ?? null;
        $this->aboutImage        = $data['aboutImage'] ?? null;
        $this->address           = $data['address'] ?? null;
        $this->phone             = $data['phone'] ?? null;
        $this->mapsEmbedUrl      = $data['mapsEmbedUrl'] ?? null;
        $this->requiredApprovals = $data['requiredApprovals'];
        $this->theme             = $data['theme'];
        $this->updatedAt         = $data['updatedAt'];
        $this->logoFile          = null;
        $this->aboutImageFile    = null;
    }

    public function __toString(): string { return $this->name; }
}
