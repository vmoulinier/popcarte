<?php

namespace App\Entity;

use App\Repository\User2FARepository;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

#[ORM\Entity(repositoryClass: User2FARepository::class)]
#[ORM\Table(name: 'user2_fa')]
class User2FA implements TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $userId = null;

    #[ORM\Column(length: 255)]
    private ?string $secret = null;

    #[ORM\Column]
    private ?bool $enabled = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $tempLoginSecret = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tempLoginExpiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->enabled = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getTempLoginSecret(): ?string
    {
        return $this->tempLoginSecret;
    }

    public function setTempLoginSecret(?string $tempLoginSecret): self
    {
        $this->tempLoginSecret = $tempLoginSecret;

        return $this;
    }

    public function getTempLoginExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tempLoginExpiresAt;
    }

    public function setTempLoginExpiresAt(?\DateTimeImmutable $tempLoginExpiresAt): self
    {
        $this->tempLoginExpiresAt = $tempLoginExpiresAt;

        return $this;
    }

    // TwoFactorInterface methods
    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTotpAuthenticationUsername(): string
    {
        // We don't have the username here, but the user ID is enough for our logic
        return (string) $this->userId;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return new TotpConfiguration($this->secret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
} 
