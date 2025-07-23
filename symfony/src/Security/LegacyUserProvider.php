<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use App\Repository\User2FARepository;
use App\Service\LegacyUserService;

class LegacyUserProvider implements UserProviderInterface
{
    private User2FARepository $user2FARepository;
    private LegacyUserService $legacyUserService;

    public function __construct(User2FARepository $user2FARepository, LegacyUserService $legacyUserService)
    {
        $this->user2FARepository = $user2FARepository;
        $this->legacyUserService = $legacyUserService;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Récupérer les vraies données de l'utilisateur legacy
        $legacyUserData = $this->legacyUserService->getLegacyUser($identifier);
        
        if (!$legacyUserData) {
            throw new UserNotFoundException("Utilisateur '$identifier' non trouvé dans le legacy.");
        }

        $userId = $legacyUserData['user_id'];
        $username = $legacyUserData['username'];
        $email = $legacyUserData['email'];
        $roles = ['ROLE_USER'];
        
        $legacyUser = new LegacyUser($userId, $username, $email, $roles);

        // Récupérer la config 2FA depuis User2FA
        $user2fa = $this->user2FARepository->findOneBy(['userId' => $userId]);
        if ($user2fa) {
            $legacyUser->setTwoFactorSecret($user2fa->getSecret());
            $legacyUser->setTwoFactorEnabled($user2fa->isEnabled());
        }

        return $legacyUser;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof LegacyUser) {
            throw new UnsupportedUserException();
        }
        // Recharger l'utilisateur depuis le legacy
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === LegacyUser::class;
    }
} 
