<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Message> */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** Unread messages directed at $user — feeds the notification badge */
    public function findUnreadByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.recipient = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'unread')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** All root messages (threads) where $user is sender or recipient */
    public function findConversations(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('r')
            ->leftJoin('m.replies', 'r')
            ->where('m.parent IS NULL')
            ->andWhere('m.sender = :user OR m.recipient = :user')
            ->setParameter('user', $user)
            ->groupBy('m.id')
            ->orderBy(
                'CASE WHEN MAX(r.sentAt) IS NULL THEN m.sentAt ELSE MAX(r.sentAt) END',
                'DESC'
            )
            ->getQuery()->getResult();
    }

    /** All conversations — admin overview (no user filter) */
    public function findAllConversations(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('r')
            ->leftJoin('m.replies', 'r')
            ->where('m.parent IS NULL')
            ->andWhere("m.status != 'resolved'")
            ->groupBy('m.id')
            ->orderBy(
                'CASE WHEN MAX(r.sentAt) IS NULL THEN m.sentAt ELSE MAX(r.sentAt) END',
                'DESC'
            )
            ->getQuery()->getResult();
    }

    public function findInboxThreads(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.replies', 'r')
            ->where('m.parent IS NULL')
            ->andWhere('m.recipient = :user OR r.recipient = :user')
            ->andWhere("m.status != 'resolved'")
            ->setParameter('user', $user)
            ->orderBy('m.sentAt', 'DESC')
            ->distinct()
            ->getQuery()->getResult();
    }

    public function findSentThreads(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.parent IS NULL')
            ->andWhere('m.sender = :user')
            ->andWhere("m.status != 'resolved'")
            ->setParameter('user', $user)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** Find root threads associated with a specific article */
    public function findByArticleContext(int $articleId): array
    {
        $messages = $this->createQueryBuilder('m')
            ->where('m.contextType = :type')
            ->andWhere('m.parent IS NULL') // only root threads
            ->setParameter('type', 'article')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()->getResult();
        
        return array_filter($messages, fn($m) => 
            $m->getContextId() !== null 
            && isset($m->getContextId()['id']) 
            && (int)$m->getContextId()['id'] === $articleId
        );
    }
}
