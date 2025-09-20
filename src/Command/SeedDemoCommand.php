<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Membership;
use App\Entity\OAuthClient;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand(name: 'app:seed-demo', description: 'Seed demo data (user/org/client)')]
class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PasswordHasherFactoryInterface $hasherFactory,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $org        = new Organization('Demo Org');
        $user       = new User('user@example.com');
        $userHasher = $this->hasherFactory->getPasswordHasher(User::class);
        $user->setPasswordHash($userHasher->hash('password'));
        $client       = new OAuthClient('Demo Client', 'demo-client');
        $clientHasher = $this->hasherFactory->getPasswordHasher(OAuthClient::class);
        $client->setSecretHash($clientHasher->hash('secret'));
        $client->setAllowedScopes(['profile.read']);
        $client->setAllowedOrgs([$org->getId()]);
        $member = new Membership($user, $org, 'member');
        $this->em->persist($org);
        $this->em->persist($user);
        $this->em->persist($client);
        $this->em->persist($member);
        $this->em->flush();
        $output->writeln('Seeded demo org='.$org->getId().' user=user@example.com password=password client_id=demo-client secret=secret');
        return Command::SUCCESS;
    }
}
