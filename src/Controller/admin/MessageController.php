<?php

namespace App\Controller\admin;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\MessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/message', name: 'admin_message_')]
class MessageController extends AbstractController
{
    /** Badge sub-request rendered in the sidebar */
    #[Route('/widget', name: 'widget', methods: ['GET'])]
    public function widget(MessageService $service): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('');
        }
        return $this->render('admin/message/_widget.html.twig', [
            'unreadCount' => $service->getUnreadCount($user),
        ]);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(MessageRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isAdmin = $user->getWorkGroup() === 0;

        return $this->render('admin/message/index.html.twig', [
            'receivedMessages' => $repo->findInboxThreads($user),
            'sentMessages'     => $repo->findSentThreads($user),
            'allMessages'      => $isAdmin ? $repo->findAllConversations() : [],
            'isAdmin'          => $isAdmin,
        ]);
    }

    /** Loads a conversation thread via AJAX — returns partial HTML */
    #[Route('/{id}', name: 'read', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function read(Message $message, MessageService $service): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($message->getRecipient() !== $user
            && $message->getSender() !== $user
            && $user->getWorkGroup() !== 0) {
            throw $this->createAccessDeniedException();
        }

        $root = $message->getRoot();
        $conversation = [$root];
        $this->collectReplies($root, $conversation);
        usort($conversation, static fn (Message $a, Message $b) => $a->getSentAt() <=> $b->getSentAt());

        foreach ($conversation as $msg) {
            if ($msg->getRecipient() === $user && $msg->getStatus() === 'unread') {
                $service->markAsRead($msg);
            }
        }

        return $this->render('admin/message/_read_modal.html.twig', [
            'conversation' => $conversation,
            'rootMessage'  => $root,
        ]);
    }

    /** Send a new message or reply (JSON API) */
    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(Request $request, MessageService $service, UserRepository $users): JsonResponse
    {
        try {
            $data        = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $recipientId = $data['recipient_id'] ?? null;
            $content     = trim((string) ($data['content'] ?? ''));

            if (!$recipientId || $content === '') {
                return new JsonResponse(['error' => 'Missing recipient or content'], 400);
            }

            $recipient = $users->find((int) $recipientId);
            if ($recipient === null) {
                return new JsonResponse(['error' => 'Recipient not found'], 404);
            }

            $contextIdInput = $data['context_id'] ?? null;
            $contextId = null;
            if (is_array($contextIdInput)) {
                $contextId = $contextIdInput;
            } elseif (is_numeric($contextIdInput)) {
                $contextId = ['id' => (int) $contextIdInput];
            } elseif (is_string($contextIdInput)) {
                $decoded = json_decode($contextIdInput, true);
                $contextId = is_array($decoded) ? $decoded : ['id' => $contextIdInput];
            }

            $msg = $service->sendMessage(
                recipient:   $recipient,
                content:     $content,
                subject:     $data['subject'] ?? null,
                contextType: $data['context_type'] ?? null,
                contextId:   $contextId,
            );

            return new JsonResponse(['status' => 'success', 'message_id' => $msg->getId()]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/reply', name: 'reply', methods: ['POST'])]
    public function reply(Message $message, Request $request, MessageService $service): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $content = trim((string) $request->request->get('content'));

        if ($content !== '') {
            $recipient = $message->getSender() === $user
                ? $message->getRecipient()
                : $message->getSender();

            $service->sendMessage(
                recipient: $recipient,
                content:   $content,
                parent:    $message,
            );
        }

        return $this->redirectToRoute('admin_message_index');
    }

    #[Route('/{id}/status/{status}', name: 'status', methods: ['POST'])]
    public function setStatus(Message $message, string $status, MessageService $service): JsonResponse
    {
        match ($status) {
            'ignored'  => $service->markAsIgnored($message),
            'resolved' => $service->markAsResolved($message),
            default    => null,
        };

        return new JsonResponse(['ok' => true]);
    }

    private function collectReplies(Message $message, array &$collection): void
    {
        foreach ($message->getReplies() as $reply) {
            $collection[] = $reply;
            $this->collectReplies($reply, $collection);
        }
    }
}
