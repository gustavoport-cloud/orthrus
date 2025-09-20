<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
class OrgVoter extends Voter
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === 'org:matches';
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof ApiUser) {
            return false;
        }
        $req = $this->requestStack->getCurrentRequest();
        if (!$req) {
            return false;
        }
        $headerOrg = (string)$req->headers->get('X-Org-Id');
        return $headerOrg !== '' && $headerOrg === $user->getOrg();
    }
}
