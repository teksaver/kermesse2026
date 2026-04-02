<?php

namespace App\Repository;

use App\Entity\LoginToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginToken>
 */
class LoginTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginToken::class);
    }

    public function findUsableBySelector(string $selector, \DateTimeImmutable $now): ?LoginToken
    {
        $token = $this->findOneBy(['selector' => $selector]);

        if (!$token instanceof LoginToken || !$token->isUsableAt($now)) {
            return null;
        }

        return $token;
    }

    public function invalidateOpenTokensForUser(User $user, \DateTimeImmutable $now): void
    {
        $this->createQueryBuilder('token')
            ->update()
            ->set('token.consumedAt', ':now')
            ->where('token.user = :user')
            ->andWhere('token.consumedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
