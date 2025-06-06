<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Message;
use App\Repository\UserRepository;
use App\Repository\FriendshipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class MessageController extends AbstractController
{
    #[Route('/api/messages', name: 'api_send_message', methods: ['POST'])]
    public function send(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        FriendshipRepository $friendshipRepo
    ): JsonResponse {
        $sender = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $receiverId = $data['receiver_id'] ?? null;
        $content = $data['content'] ?? '';

        if (!$receiverId || empty($content)) {
            return $this->json(['error' => 'receiver_id and content are required'], 400);
        }

        $receiver = $userRepo->find($receiverId);
        if (!$receiver) {
            return $this->json(['error' => 'Receiver not found'], 404);
        }

        // Vérifier si le destinataire est un ami
        $friendship = $friendshipRepo->findOneBy([
            'user1' => $sender,
            'user2' => $receiver,
            'status' => 'accepted'
        ]) ?? $friendshipRepo->findOneBy([
            'user1' => $receiver,
            'user2' => $sender,
            'status' => 'accepted'
        ]);

        if (!$friendship) {
            return $this->json(['error' => 'You can only message friends.'], 403);
        }

        $message = new Message();
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setIsRead(false);
        $message->setSentAt(new \DateTimeImmutable());

        $em->persist($message);
        $em->flush();

        return $this->json(['message' => 'Message sent.']);
    }

    #[Route('/api/messages/{userId}', name: 'api_conversation', methods: ['GET'])]
    public function conversation(
        int $userId,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $currentUser = $this->getUser();
        $otherUser = $userRepo->find($userId);

        if (!$otherUser) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $messages = $em->getRepository(Message::class)->createQueryBuilder('m')
            ->where('(m.sender = :u1 AND m.receiver = :u2) OR (m.sender = :u2 AND m.receiver = :u1)')
            ->setParameter('u1', $currentUser)
            ->setParameter('u2', $otherUser)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($messages as $msg) {
            if ($msg->getReceiver() === $currentUser && !$msg->isRead()) {
                $msg->setIsRead(true);
            }

            $data[] = [
                'id' => $msg->getId(),
                'from' => $msg->getSender()->getId(),
                'to' => $msg->getReceiver()->getId(),
                'content' => $msg->getContent(),
                'sentAt' => $msg->getSentAt()->format('Y-m-d H:i:s'),
            ];
        }
        $em->flush();

        return $this->json($data);
    }

    #[Route('/api/messages/unread-count', name: 'api_unread_message_count', methods: ['GET'])]
    public function unreadCount(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $count = $em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(\App\Entity\Message::class, 'm')
            ->where('m.receiver = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json(['unread' => (int) $count]);
    }

    #[Route('/api/messages/unread-count-by-friend', name: 'api_unread_by_friend', methods: ['GET'])]
    public function unreadByFriend(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $results = $em->createQuery(
            'SELECT IDENTITY(m.sender) AS senderId, COUNT(m.id) AS count
         FROM App\Entity\Message m
         WHERE m.receiver = :user AND m.isRead = false
         GROUP BY m.sender'
        )->setParameter('user', $user)->getResult();

        $mapped = [];
        foreach ($results as $r) {
            $mapped[$r['senderId']] = (int) $r['count'];
        }

        return $this->json($mapped);
    }
}
