<?php

// src/Security/Controller/AuthController.php

declare(strict_types=1);

namespace App\Security\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller d'authentification.
 * La route /api/auth/login est interceptée par le firewall Symfony Security
 * (json_login) avant d'atteindre le controller — ce code n'est jamais exécuté.
 */
final class AuthController extends AbstractController
{
    /**
     * Point d'entrée du login JWT.
     * Intercepté par json_login du firewall → retourne un token JWT.
     *
     * POST /api/auth/login
     * Body : {"email": "...", "password": "..."}
     */
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Ce code n'est jamais atteint : le firewall intercepte avant.
        return new JsonResponse(
            ['error' => 'Non autorisé.'],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
