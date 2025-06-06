<?php

namespace App\Repository;

use App\Entity\Friendship;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

/**
 * @extends ServiceEntityRepository<Friendship>
 */
class FriendshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Friendship::class);
    }

    public function findAcceptedFriends(User $user): array
    {
        $qb = $this->createQueryBuilder('f');
        $qb->where('f.status = :status')
            ->andWhere('f.user1 = :user OR f.user2 = :user')
            ->setParameter('status', 'accepted')
            ->setParameter('user', $user);

        $friendships = $qb->getQuery()->getResult();

        $friends = [];
        foreach ($friendships as $f) {
            if ($f->getUser1() === $user) {
                $friends[] = $f->getUser2();
            } else {
                $friends[] = $f->getUser1();
            }
        }

        return $friends;
    }
}
