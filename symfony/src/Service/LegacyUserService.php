<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class LegacyUserService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Récupère un utilisateur legacy par son ID ou username
     */
    public function getLegacyUser(string $identifier): ?array
    {
        try {
            $sql = "SELECT user_id, username, email, fname, lname FROM users WHERE ";
            
            if (is_numeric($identifier)) {
                $sql .= "user_id = :identifier";
            } else {
                $sql .= "username = :identifier";
            }
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue('identifier', $identifier);
            $result = $stmt->executeQuery();
            
            $userData = $result->fetchAssociative();
            
            if ($userData) {
                return [
                    'user_id' => (int)$userData['user_id'],
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'first_name' => $userData['fname'],
                    'last_name' => $userData['lname']
                ];
            }
            
            return null;
        } catch (Exception $e) {
            // Log l'erreur et retourne null
            error_log("Erreur lors de la récupération de l'utilisateur legacy: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie si un utilisateur existe
     */
    public function userExists(string $identifier): bool
    {
        return $this->getLegacyUser($identifier) !== null;
    }
} 
