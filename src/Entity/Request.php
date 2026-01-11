<?php

namespace App\Entity;

use App\Repository\RequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
class Request
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;



    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $datedemande = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userid = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $Articleid = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Article $articleprop = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $idrequest = null;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }
    public function getIdrequest(): ?int
    {
        return $this->idrequest;
    }

    public function setIdrequest(int $idrequest): static
    {
        $this->idrequest = $idrequest;

        return $this;
    }

    public function getDatedemande(): ?\DateTimeInterface
    {
        return $this->datedemande;
    }

    public function setDatedemande(\DateTimeInterface $datedemande): static
    {
        $this->datedemande = $datedemande;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUserid(): ?User
    {
        return $this->userid;
    }

    public function setUserid(?User $userid): static
    {
        $this->userid = $userid;

        return $this;
    }

    public function getArticleid(): ?Article
    {
        return $this->Articleid;
    }

    public function setArticleid(?Article $Articleid): static
    {
        $this->Articleid = $Articleid;

        return $this;
    }

    public function getArticleprop(): ?Article
    {
        return $this->articleprop;
    }

    public function setArticleprop(?Article $articleprop): static
    {
        $this->articleprop = $articleprop;

        return $this;
    }
}
