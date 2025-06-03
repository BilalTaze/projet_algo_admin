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
            $data[] = [
                'id' => $msg->getId(),
                'from' => $msg->getSender()->getId(),
                'to' => $msg->getReceiver()->getId(),
                'content' => $msg->getContent(),
                'sentAt' => $msg->getSentAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }
}
