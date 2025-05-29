<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;



class Message {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $sender;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $receiver;
}