<?php

namespace App\Entity;

use App\Repository\UserRepository;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Form\Extension\Core\Type\EntityType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;
    #[ORM\Column(length: 255)]
private ?string $firstname = null;
    #[ORM\Column(length: 255)]
    private ?string $lastname = null;
    #[ORM\Column(length: 255)]
    private ?string $phonenum = null;
    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $pdp = null;

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function getPhonenum(): ?string
    {
        return $this->phonenum;
    }

    public function setPhonenum(?string $phonenum): void
    {
        $this->phonenum = $phonenum;
    }

    /**
     * @return null
     */
    public function getPdp(): mixed
    {
        return $this->pdp;
    }

    /**
     * @param null $pdp
     */
    public function setPdp(?string $pdp): self
    {
        $this->pdp = $pdp;
        return $this;
    }
    private ?string $encodedpdp = null;

    public function getEncodedPdp(): ?string
    {

            return $this->pdp ? base64_encode(stream_get_contents($this->pdp)) : null;

    }

    public function setEncodedpdp(?string $encodedpdp): self
    {
        $this->encodedpdp = $encodedpdp;
        return $this;

    }
    public function __toString(): string
    {
        return $this->getUserIdentifier(); // or whatever property you'd like to use
    }
    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
