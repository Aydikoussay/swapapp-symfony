<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $conditionarticle = null;
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $numberOfOffers = 0;
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Categorie $categorie = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $photo1 = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $photo2 = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $photo3 = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getConditionarticle(): ?string
    {
        return $this->conditionarticle;
    }

    public function setConditionarticle(?string $conditionarticle): self
    {
        $this->conditionarticle = $conditionarticle;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPhoto1(): mixed
    {
        return $this->photo1;
    }
    public function getEncodedPhoto1(): ?string
    {
        return $this->photo1 ? base64_encode(stream_get_contents($this->photo1)) : null;
    }

    public function getEncodedPhoto2(): ?string
    {
        return $this->photo2 ? base64_encode(stream_get_contents($this->photo2)) : null;
    }

    public function getEncodedPhoto3(): ?string
    {
        return $this->photo3 ? base64_encode(stream_get_contents($this->photo3)) : null;
    }
    public function setPhoto1($photo1): self
    {
        $this->photo1 = $photo1;
        return $this;
    }

    public function getPhoto2(): mixed
    {
        return $this->photo2;
    }
    private ?string $encodedPhoto1 = null;


    public function setEncodedPhoto1(?string $encodedPhoto1): self
    {
        $this->encodedPhoto1 = $encodedPhoto1;
        return $this;
    }

// Encoded Photo 2
    private ?string $encodedPhoto2 = null;



    public function setEncodedPhoto2(?string $encodedPhoto2): self
    {
        $this->encodedPhoto2 = $encodedPhoto2;
        return $this;
    }

// Encoded Photo 3
    private ?string $encodedPhoto3 = null;



    public function setEncodedPhoto3(?string $encodedPhoto3): self
    {
        $this->encodedPhoto3 = $encodedPhoto3;
        return $this;
    }
    public function setPhoto2($photo2): self
    {
        $this->photo2 = $photo2;
        return $this;
    }

    public function getPhoto3(): mixed
    {
        return $this->photo3;
    }

    public function setPhoto3($photo3): self
    {
        $this->photo3 = $photo3;
        return $this;
    }
    public function getNumberOfOffers(): ?int
    {
        return $this->numberOfOffers;
    }

    public function setNumberOfOffers(int $numberOfOffers): self
    {
        $this->numberOfOffers = $numberOfOffers;
        return $this;
    }
}
