<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StudyApproval
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Study::class, inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Study $study = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $reviewer = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $approvedAt;

    public function __construct()
    {
        $this->approvedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getStudy(): ?Study { return $this->study; }
    public function setStudy(?Study $study): static { $this->study = $study; return $this; }

    public function getReviewer(): ?User { return $this->reviewer; }
    public function setReviewer(?User $reviewer): static { $this->reviewer = $reviewer; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }

    public function getApprovedAt(): \DateTimeImmutable { return $this->approvedAt; }
}
