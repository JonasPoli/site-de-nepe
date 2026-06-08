<?php

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterSubscriber> */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, NewsletterSubscriber::class); }

    /** Verifica duplicidade apenas dentro do mesmo tenant */
    public function emailExists(string $email, ?Tenant $tenant = null): bool
    {
        $criteria = ['email' => $email];
        if ($tenant) {
            $criteria['tenant'] = $tenant;
        }
        return (bool) $this->findOneBy($criteria);
    }

    /** Lista inscritos do tenant atual */
    public function findByTenant(Tenant $tenant): array
    {
        return $this->findBy(['tenant' => $tenant], ['subscribedAt' => 'DESC']);
    }
}
