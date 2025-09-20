<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\ApiUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MeController extends AbstractController
{
    #[Route(path: '/me', name: 'me', methods: ['GET'])]
    #[IsGranted('scope:profile.read')]
    #[IsGranted('org:matches')]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof ApiUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        return $this->json([
            'sub'    => $user->getUserIdentifier(),
            'org'    => $user->getOrg(),
            'scopes' => $user->getScopes(),
            'client' => $user->isClient(),
        ]);
    }
}
