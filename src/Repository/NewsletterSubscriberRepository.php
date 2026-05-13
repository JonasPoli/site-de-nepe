<?php

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterSubscriber> */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, NewsletterSubscriber::class); }

    public function emailExists(string $email): bool
    {
        return (bool) $this->findOneBy(['email' => $email]);
    }
}
