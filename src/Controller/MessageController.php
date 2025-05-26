<?php



namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
    #[Route('/messages', name: 'messages')]
    public function index(): Response
    {
        return new Response('<h1>Bienvenue dans la messagerie TSN !</h1>');
    }
}

?>