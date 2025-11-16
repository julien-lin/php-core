<?php

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * Interface pour les middlewares du framework Core PHP
 * Compatible avec php-router
 */
interface MiddlewareInterface
{
    /**
     * Traite la requête
     *
     * @param Request $request Requête HTTP
     * @return Response|null Réponse si le middleware arrête l'exécution, null pour continuer
     */
    public function handle(Request $request): ?Response;
}

