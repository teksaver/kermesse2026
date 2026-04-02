<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    public function findActiveSession(string $sessionId, int $userId, \DateTimeImmutable $now): ?UserSession
    {
        $session = $this->createQueryBuilder('session')
            ->join('session.user', 'user')
            ->addSelect('user')
            ->where('session.sessionId = :sessionId')
            ->andWhere('user.id = :userId')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$session instanceof UserSession || !$session->isActiveAt($now)) {
            return null;
        }

        return $session;
    }
}
