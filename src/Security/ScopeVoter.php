<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
class ScopeVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    {
        return str_starts_with($attribute, 'scope:');
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof ApiUser) {
            return false;
        }
        $required = substr($attribute, strlen('scope:'));
        return in_array($required, $user->getScopes(), true);
    }
}
