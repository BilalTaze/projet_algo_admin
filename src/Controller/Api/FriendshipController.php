<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Friendship;
use App\Repository\UserRepository;
use App\Repository\FriendshipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class FriendshipController extends AbstractController
{
    #[Route('/api/friends', name: 'api_friends_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $friends = [];

        $initiated = $em->getRepository(Friendship::class)->findBy([
            'user1' => $user,
            'status' => 'accepted'
        ]);

        $received = $em->getRepository(Friendship::class)->findBy([
            'user2' => $user,
            'status' => 'accepted'
        ]);

        foreach ($initiated as $f) {
            $u = $f->getUser2();
            $friends[] = [
                'id' => $u->getId(),
                'name' => $u->getName(),
                'email' => $u->getEmail(),
            ];
        }

        foreach ($received as $f) {
            $u = $f->getUser1();
            $friends[] = [
                'id' => $u->getId(),
                'name' => $u->getName(),
                'email' => $u->getEmail(),
            ];
        }

        return $this->json($friends);
    }


    #[Route('/api/friends/pending', name: 'api_pending_requests', methods: ['GET'])]
    public function pending(FriendshipRepository $friendshipRepo): JsonResponse
    {
        $currentUser = $this->getUser();

        $requests = $friendshipRepo->findBy([
            'user2' => $currentUser,
            'status' => 'pending',
        ]);

        $data = [];
        foreach ($requests as $friendship) {
            $data[] = [
                'id' => $friendship->getId(),
                'from' => [
                    'id' => $friendship->getUser1()->getId(),
                    'name' => $friendship->getUser1()->getName(),
                    'email' => $friendship->getUser1()->getEmail(),
                ],
                'sentAt' => $friendship->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/friends/accept', name: 'api_accept_friend_request', methods: ['POST'])]
    public function accept(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $friendshipId = $data['friendship_id'] ?? null;
        if (!$friendshipId) {
            return $this->json(['error' => 'friendship_id is required'], 400);
        }

        $friendship = $em->getRepository(Friendship::class)->find($friendshipId);
        if (!$friendship || $friendship->getUser2() !== $currentUser) {
            return $this->json(['error' => 'Friend request not found or unauthorized'], 404);
        }

        $friendship->setStatus('accepted');
        $em->flush();

        return $this->json(['message' => 'Friend request accepted.']);
    }

    #[Route('/api/friends/send', name: 'api_send_friend_request', methods: ['POST'])]
    public function send(Request $request, UserRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $targetId = $data['target_user_id'] ?? null;

        if (!$targetId) {
            return $this->json(['error' => 'target_user_id is required'], 400);
        }

        if ($targetId == $currentUser->getId()) {
            return $this->json(['error' => 'You cannot send a friend request to yourself'], 400);
        }

        $targetUser = $userRepo->find($targetId);
        if (!$targetUser) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // Vérifie si une relation existe déjà
        $existing = $em->getRepository(Friendship::class)->findOneBy([
            'user1' => $currentUser,
            'user2' => $targetUser,
        ]);

        if ($existing) {
            return $this->json(['error' => 'Friend request already sent'], 400);
        }

        $friendship = new Friendship();
        $friendship->setUser1($currentUser);
        $friendship->setUser2($targetUser);
        $friendship->setStatus('pending');
        $friendship->setCreatedAt(new \DateTimeImmutable());

        $em->persist($friendship);
        $em->flush();

        return $this->json(['message' => 'Friend request sent']);
    }
}
