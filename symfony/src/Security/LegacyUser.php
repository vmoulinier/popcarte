<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

class LegacyUser implements UserInterface, TwoFactorInterface
{
    private int $id;
    private string $username;
    private string $email;
    private array $roles;
    private ?string $twoFactorSecret = null;
    private bool $twoFactorEnabled = false;

    public function __construct(int $id, string $username, string $email, array $roles = ['ROLE_USER'])
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->roles = $roles;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void {}

    public function getPassword(): ?string
    {
        return null; // Le mot de passe est géré côté legacy
    }

    public function getSalt(): ?string
    {
        return null;
    }

    // Ces setters seront appelés par le provider ou le bridge pour injecter les infos User2FA
    public function setTwoFactorSecret(?string $secret): void
    {
        $this->twoFactorSecret = $secret;
    }

    public function setTwoFactorEnabled(bool $enabled): void
    {
        $this->twoFactorEnabled = $enabled;
    }

    // Méthodes requises par l'interface TwoFactorInterface (scheb/2fa-totp)
    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username; // ou email si tu préfères
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        // Si l'utilisateur a déjà un secret, on l'utilise
        if ($this->twoFactorSecret) {
            return new TotpConfiguration(
                $this->twoFactorSecret,
                TotpConfiguration::ALGORITHM_SHA1,
                30,
                6
            );
        }
        
        // Sinon, on génère un nouveau secret temporaire pour l'activation
        // Ce secret sera sauvegardé une fois que l'utilisateur valide le code
        $tempSecret = $this->generateTemporarySecret();
        return new TotpConfiguration(
            $tempSecret,
            TotpConfiguration::ALGORITHM_SHA1,
            30,
            6
        );
    }

    private function generateTemporarySecret(): string
    {
        // Générer un secret temporaire pour l'activation
        // Ce secret sera remplacé par le vrai secret une fois validé
        $bytes = random_bytes(20);
        return $this->base32Encode($bytes);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $encoded = '';
        
        // Convertir les bytes en bits
        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        
        // Encoder en base32
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }
        
        return $encoded;
    }
} 
