<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }
    public function findVisiblePostsForUser(User $currentUser): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC');

        return $qb->where('p.visibility = :public')
            ->orWhere('p.author = :user')
            ->orWhere(
                $qb->expr()->andX(
                    $qb->expr()->eq('p.visibility', ':friends'),
                    $qb->expr()->in('p.author', ':friendList')
                )
            )
            ->setParameter('public', 'public')
            ->setParameter('user', $currentUser)
            ->setParameter('friends', 'friends')
            ->setParameter('friendList', $this->getFriendIds($currentUser))
            ->getQuery()
            ->getResult();
    }

    private function getFriendIds(User $user): array
    {
        $friends = [];

        // amitiés envoyées et acceptées
        foreach ($user->getFriendshipsInitiated() as $f) {
            if ($f->getStatus() === 'accepted') {
                $friends[] = $f->getUser2()->getId();
            }
        }

        // amitiés reçues et acceptées
        foreach ($user->getFriendshipsReceived() as $f) {
            if ($f->getStatus() === 'accepted') {
                $friends[] = $f->getUser1()->getId();
            }
        }

        return $friends;
    }
}
