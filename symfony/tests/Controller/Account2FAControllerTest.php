<?php

namespace App\Tests\Controller;

use App\Entity\User2FA;
use App\Repository\User2FARepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Account2FAControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $user2FARepository;
    private $totpAuthenticator;
    private $logger;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->user2FARepository = static::getContainer()->get(User2FARepository::class);
        $this->totpAuthenticator = static::getContainer()->get(TotpAuthenticatorInterface::class);
        $this->logger = static::getContainer()->get(LoggerInterface::class);
    }

    public function testIndexPageWithoutAuthenticatedUser()
    {
        $this->client->request('GET', '/account/2fa');
        
        // Devrait rediriger vers le legacy login
        $this->assertResponseRedirects('/Web/index.php');
    }

    public function testIndexPageWithAuthenticatedUser()
    {
        // Simuler un utilisateur authentifié
        $this->client->request('GET', '/account/2fa');
        
        // Pour ce test, nous devons mocker l'authentification
        // Ceci nécessiterait une configuration plus complexe
        $this->markTestSkipped('Requiert une configuration d\'authentification complexe');
    }

    public function testActivate2FAWithValidCode()
    {
        // Créer un utilisateur 2FA de test
        $user2fa = new User2FA();
        $user2fa->setUserId('123');
        $user2fa->setSecret('TESTSECRET123');
        $user2fa->setEnabled(false);
        
        $this->entityManager->persist($user2fa);
        $this->entityManager->flush();

        // Mock du TOTP authenticator pour retourner true
        $this->totpAuthenticator->method('checkCode')
            ->willReturn(true);

        $this->client->request('POST', '/account/2fa/activate', [
            'user_id' => '123',
            'code' => '123456'
        ]);

        // Vérifier que la 2FA est activée
        $this->entityManager->refresh($user2fa);
        $this->assertTrue($user2fa->isEnabled());
        $this->assertNotNull($user2fa->getTempLoginSecret());
    }

    public function testActivate2FAWithInvalidCode()
    {
        // Créer un utilisateur 2FA de test
        $user2fa = new User2FA();
        $user2fa->setUserId('123');
        $user2fa->setSecret('TESTSECRET123');
        $user2fa->setEnabled(false);
        
        $this->entityManager->persist($user2fa);
        $this->entityManager->flush();

        // Mock du TOTP authenticator pour retourner false
        $this->totpAuthenticator->method('checkCode')
            ->willReturn(false);

        $this->client->request('POST', '/account/2fa/activate', [
            'user_id' => '123',
            'code' => '000000'
        ]);

        // Vérifier que la 2FA n'est pas activée
        $this->entityManager->refresh($user2fa);
        $this->assertFalse($user2fa->isEnabled());
    }

    public function testDisable2FA()
    {
        // Créer un utilisateur 2FA activé
        $user2fa = new User2FA();
        $user2fa->setUserId('123');
        $user2fa->setSecret('TESTSECRET123');
        $user2fa->setEnabled(true);
        
        $this->entityManager->persist($user2fa);
        $this->entityManager->flush();

        $this->client->request('POST', '/account/2fa/disable');

        // Vérifier que la 2FA est désactivée
        $this->entityManager->refresh($user2fa);
        $this->assertFalse($user2fa->isEnabled());
    }

    protected function tearDown(): void
    {
        // Nettoyer la base de données de test
        $this->entityManager->createQuery('DELETE FROM App\Entity\User2FA')->execute();
        parent::tearDown();
    }
} 
