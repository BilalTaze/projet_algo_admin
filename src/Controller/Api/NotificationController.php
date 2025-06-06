<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Notification;

final class NotificationController extends AbstractController
{
    #[Route('/api/notifications', name: 'api_notifications', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $em->getRepository(Notification::class)->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        $data = [];
        foreach ($notifications as $notif) {
            $data[] = [
                'id' => $notif->getId(),
                'type' => $notif->getType(),
                'content' => $notif->getContent(),
                'isRead' => $notif->isRead(),
                'createdAt' => $notif->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/notifications/read/{id}', name: 'api_notifications_read', methods: ['POST'])]
    public function markAsRead(Notification $notification, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if ($notification->getOwner() !== $user) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $notification->setIsRead(true);
        $em->flush();

        return $this->json(['message' => 'Notification marquée comme lue']);
    }

    #[Route('/api/notifications/mark-as-read', name: 'api_notifications_mark_as_read', methods: ['POST'])]
    public function markAllAsRead(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $notifications = $em->getRepository(Notification::class)->findBy(['owner' => $user, 'isRead' => false]);

        foreach ($notifications as $notif) {
            $notif->setIsRead(true);
        }

        $em->flush();

        return $this->json(['message' => 'Notifications marked as read']);
    }
}
