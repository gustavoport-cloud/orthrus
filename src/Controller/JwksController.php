<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JwksService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class JwksController extends AbstractController
{
    #[Route(path: '/.well-known/jwks.json', name: 'jwks', methods: ['GET'])]
    public function jwks(JwksService $jwks): JsonResponse
    {
        $res = new JsonResponse($jwks->getJwks());
        $res->setPublic();
        $res->setMaxAge(300);
        return $res;
    }
}
