<?php

namespace App\Service;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class MessageService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    public function sendMessage(
        User $recipient,
        string $content,
        ?string $subject = null,
        ?string $contextType = null,
        ?array $contextId = null,
        ?Message $parent = null,
    ): Message {
        /** @var User $sender */
        $sender = $this->security->getUser();
        if (!$sender instanceof User) {
            throw new \LogicException('User must be authenticated to send messages.');
        }

        $message = new Message();
        $message->setSender($sender);
        $message->setRecipient($recipient);
        $message->setContent($content);
        $message->setSubject($subject);
        $message->setContextType($contextType);
        $message->setContextId($contextId);
        $message->setParent($parent);

        if ($parent !== null) {
            $message->setSubject('Re: ' . ($parent->getSubject() ?? 'Sem assunto'));

            // Walk to thread root and reactivate if resolved
            $root = $parent->getRoot();
            if (in_array($root->getStatus(), ['resolved', 'read', 'unread'], true)) {
                $root->setStatus('replied');
            }
            $parent->setStatus('replied');
        }

        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    /** Idempotent — preserves the original readAt date */
    public function markAsRead(Message $message): void
    {
        if ($message->getReadAt() !== null) {
            return;
        }
        $message->setReadAt(new \DateTimeImmutable());
        if ($message->getStatus() === 'unread') {
            $message->setStatus('read');
        }
        $this->em->flush();
    }

    public function markAsIgnored(Message $message): void
    {
        $message->setStatus('ignored');
        $this->em->flush();
    }

    public function markAsResolved(Message $message): void
    {
        $message->setStatus('resolved');
        $this->em->flush();
    }

    public function getUnreadCount(User $user): int
    {
        return count($this->messageRepository->findUnreadByUser($user));
    }
}
