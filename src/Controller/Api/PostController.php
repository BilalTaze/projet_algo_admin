<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\PostRepository;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Post;

final class PostController extends AbstractController
{
    #[Route('/api/posts', name: 'api_posts_list', methods: ['GET'])]
    public function index(PostRepository $postRepository): JsonResponse
    {
        $user = $this->getUser();
        $posts = $postRepository->findVisiblePostsForUser($user);

        $data = [];

        foreach ($posts as $post) {
            $data[] = [
                'id' => $post->getId(),
                'author' => $post->getAuthor()->getName(),
                'content' => $post->getContent(),
                'visibility' => $post->getVisibility(),
                'createdAt' => $post->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/posts', name: 'api_posts_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        $post = new Post();
        $post->setAuthor($user);
        $post->setContent($data['content'] ?? '');
        $post->setVisibility($data['visibility'] ?? 'public');
        $post->setCreatedAt(new \DateTimeImmutable());

        $em->persist($post);
        $em->flush();

        return $this->json(['status' => 'Post created'], 201);
    }
}
