# Sistema de Comunicação e Aprovação de Artigos/Notícias — Documentação Técnica

> **Objetivo**: Explicar de forma exaustiva como funcionam o sistema de comunicação entre usuários e o sistema de aprovação de artigos/notícias, de modo que um programador iniciante consiga replicar estas funcionalidades em qualquer projeto Symfony 7.

> **Contexto de origem**: Este documento foi extraído de um projeto real usando Symfony 7, Doctrine ORM, Twig, Tailwind CSS e Shoelace (biblioteca de componentes web). O módulo aqui chamado de "artigo/notícia" era originalmente chamado de "paratexto" — o código-fonte usa o nome `Paratext`, mas conceitualmente é qualquer conteúdo editorial que passa por um fluxo de criação → revisão → aprovação.

---

## Índice

1. [Usuários e Hierarquia de Grupos de Trabalho](#parte-1)
2. [Sistema de Permissões com Voter](#parte-2)
3. [A Entidade Message — Banco de Dados](#parte-3)
4. [O MessageRepository — Queries](#parte-4)
5. [O MessageService — Lógica de Negócio](#parte-5)
6. [O MessageController — Rotas e Actions](#parte-6)
7. [Templates do Sistema de Mensagens](#parte-7)
8. [Integração do Widget no Layout](#parte-8)
9. [Mensagens Contextuais (iniciadas de dentro do artigo)](#parte-9)
10. [O Sistema de Artigos/Notícias — CRUD Completo](#parte-10)
11. [Fluxo Completo: Da Criação à Aprovação](#parte-11)
12. [Guia de Replicação em Novo Projeto](#parte-12)

---

<a name="parte-1"></a>
## PARTE 1 — Usuários e Hierarquia de Grupos de Trabalho

### 1.1 A Entidade `User`

**Arquivo de referência**: `src/Entity/User.php`

A entidade `User` é a base de todo o sistema de permissões. Ela implementa as interfaces do Symfony Security (`UserInterface` e `PasswordAuthenticatedUserInterface`).

**Campos da tabela `user` no banco de dados**:

| Campo | Tipo Doctrine | Tipo PHP | Descrição |
|---|---|---|---|
| `id` | `integer` (auto-increment) | `?int` | Chave primária |
| `username` | `string(180)` + UniqueConstraint | `?string` | Login do usuário (único no banco) |
| `name` | `string(255)` | `?string` | Nome completo — exibido na interface |
| `email` | `string(255)` + `unique: true` | `?string` | E-mail (único no banco) |
| `workGroup` | `integer` (default: 0) | `int` | **Grupo de trabalho** — define o perfil e permissões |
| `roles` | `json` | `array` | Array de roles extras do Symfony (normalmente `[]`) |
| `password` | `string` | `?string` | Hash bcrypt da senha |

#### O campo `workGroup` — o coração do sistema de permissões

O campo `workGroup` é um inteiro simples que determina qual papel o usuário exerce no sistema. Todo o sistema de permissões gira em torno dele.

| Valor | Perfil | O que pode fazer |
|---|---|---|
| **0** | **Administrador** | Acesso total. Vê todas as mensagens de todos. Gerencia usuários e todo conteúdo. |
| **1** | **Editor / Autor** | Cria e edita artigos/notícias. Recebe mensagens de revisores. |
| **2** | **Revisor** | Lê artigos. Não pode editar. Pode enviar comentários/sugestões ao autor. |

> **Adapte conforme seu projeto**: os valores de `workGroup` são totalmente customizáveis. Adicione quantos perfis precisar (ex.: 3 = Editor-Chefe, 4 = Aprovador Final).

#### O método `getRoles()` — como os roles Symfony são montados

```php
public function getRoles(): array
{
    $roles = $this->roles;

    // Todo usuário autenticado sempre tem ROLE_USER
    $roles[] = 'ROLE_USER';

    // O Admin (workGroup 0) ganha ROLE_ADMIN automaticamente
    if ($this->workGroup === 0) {
        $roles[] = 'ROLE_ADMIN';
    }

    return array_unique($roles);
}
```

**Ponto importante**: O campo `roles` no banco de dados pode estar vazio (`[]`) para a maioria dos usuários — isso é normal. O `workGroup` é quem realmente controla o acesso via Voter. O Symfony chama `getRoles()` em cada requisição para montar os roles do token de segurança.

#### Código completo da entidade User (simplificado)

```php
<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $workGroup = 0;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        if ($this->workGroup === 0) {
            $roles[] = 'ROLE_ADMIN';
        }
        return array_unique($roles);
    }

    // ... getters e setters omitidos para brevidade
}
```

### 1.2 CRUD de Usuários

**Controller**: `src/Controller/Admin/UserController.php`
**Templates**: `templates/admin/user/`
**Rota base**: `/admin/user`

Restrito ao Administrador (`workGroup == 0`). Permite criar, listar, editar e deletar usuários. O campo `workGroup` é editável pelo admin, permitindo promover ou rebaixar usuários de perfil a qualquer momento.

---

<a name="parte-2"></a>
## PARTE 2 — Sistema de Permissões Avançadas (Voter)

### 2.1 O que é um Voter no Symfony?

Um Voter é uma classe PHP que o Symfony consulta quando você usa:
- `is_granted('NOME_DA_PERMISSAO')` em um template Twig
- `#[IsGranted('NOME_DA_PERMISSAO')]` em um controller

O Voter recebe o nome da permissão e o usuário logado, e decide `true` (acesso permitido) ou `false` (acesso negado). É o lugar correto para centralizar regras de negócio de acesso.

### 2.2 O `UserVoter`

**Arquivo**: `src/Security/UserVoter.php`

```php
<?php
namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    // Permissões de Artigo/Notícia
    public const CAN_EDIT_ARTICLE    = 'CAN_EDIT_ARTICLE';    // Criar e editar artigos
    public const CAN_COMMENT_ARTICLE = 'CAN_COMMENT_ARTICLE'; // Comentar/revisar artigos

    // Permissões globais
    public const CAN_EXPORT = 'CAN_EXPORT'; // Exportar conteúdo

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::CAN_EDIT_ARTICLE,
            self::CAN_COMMENT_ARTICLE,
            self::CAN_EXPORT,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false; // Usuário não autenticado = sem acesso
        }

        // Admin (workGroup 0) tem acesso a TUDO — verificado antes do match
        if ($user->getWorkGroup() === 0) {
            return true;
        }

        return match ($attribute) {
            self::CAN_EDIT_ARTICLE    => $user->getWorkGroup() === 1, // Só editores
            self::CAN_COMMENT_ARTICLE => $user->getWorkGroup() === 2, // Só revisores
            self::CAN_EXPORT          => $user->getWorkGroup() === 1, // Só editores
            default => false,
        };
    }
}
```

### 2.3 Tabela de Permissões por WorkGroup

| Permissão | Admin (0) | Editor (1) | Revisor (2) |
|---|:---:|:---:|:---:|
| `CAN_EDIT_ARTICLE` | ✅ | ✅ | ❌ |
| `CAN_COMMENT_ARTICLE` | ✅ | ❌ | ✅ |
| `CAN_EXPORT` | ✅ | ✅ | ❌ |
| `ROLE_ADMIN` (Symfony) | ✅ | ❌ | ❌ |

### 2.4 Como usar as permissões

**Em um template Twig** — para mostrar/esconder elementos:
```twig
{% if is_granted('CAN_EDIT_ARTICLE') %}
    <a href="{{ path('app_admin_article_new') }}">Novo Artigo</a>
{% endif %}
```

**Em um controller com atributo** — bloqueia a action inteira (retorna 403 se negado):
```php
#[IsGranted('CAN_EDIT_ARTICLE')]
public function new(Request $request, EntityManagerInterface $em): Response
{
    // Só chega aqui se o voter retornar true
}
```

**Verificação direta do workGroup** (para lógicas específicas não cobertas pelo Voter):
```twig
{% if app.user.workGroup == 0 %}
    {# Só admin vê isso #}
{% endif %}
```

**Verificação no PHP do controller** (sem o atributo):
```php
if ($this->isGranted('CAN_EDIT_ARTICLE')) {
    // ...
}
// ou
if ($user->getWorkGroup() === 0) {
    // admin
}
```


---

<a name="parte-3"></a>
## PARTE 3 — A Entidade `Message` (Banco de Dados)

### 3.1 Estrutura da Tabela

**Arquivo**: `src/Entity/Message.php`

A entidade `Message` suporta tanto mensagens diretas entre usuários quanto mensagens vinculadas a um artigo/notícia específico do sistema (mensagens contextuais).

| Campo | Tipo Doctrine | Descrição |
|---|---|---|
| `id` | `integer` (auto-increment) | Chave primária |
| `sender` | `ManyToOne → User` (nullable: false) | Quem enviou a mensagem |
| `recipient` | `ManyToOne → User` (nullable: false) | Quem recebe a mensagem |
| `subject` | `string(255)` nullable | Assunto da mensagem |
| `content` | `TEXT` | Corpo da mensagem |
| `sentAt` | `DateTimeImmutable` | Data/hora do envio (preenchida automaticamente no construtor) |
| `readAt` | `DateTimeImmutable` nullable | Data/hora em que o destinatário leu pela primeira vez |
| `status` | `string(20)` (default: `'unread'`) | Estado atual da conversa |
| `contextType` | `string(50)` nullable | Tipo do objeto vinculado: ex. `'article'` |
| `contextId` | `JSON` nullable | Identificador do objeto vinculado (array JSON) |
| `parent` | `ManyToOne → Message` (self-ref) | Mensagem pai — `null` indica mensagem raiz |
| `replies` | `OneToMany → Message` | Coleção de respostas a esta mensagem |

### 3.2 Código completo da entidade

```php
<?php
namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'unread'; // unread, read, ignored, replied, resolved

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contextType = null; // ex: 'article'

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contextId = null; // ex: ['id' => 42]

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $replies;

    public function __construct()
    {
        $this->sentAt  = new \DateTimeImmutable(); // Data atual automática
        $this->replies = new ArrayCollection();    // Coleção vazia de respostas
    }

    // ... getters e setters
}
```

### 3.3 O Auto-relacionamento — Threads de Conversa

A entidade referencia a si mesma para criar a estrutura de **thread** (fio de conversa):

```php
// Referência para a mensagem pai (null = esta é a mensagem raiz)
#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
private ?self $parent = null;

// Coleção de respostas a esta mensagem
#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
private Collection $replies;
```

**Exemplo visual de uma thread no banco**:
```
Message #1 (raiz): parent=null, sender=João, recipient=Maria
  └─ Message #5 (resposta): parent=1, sender=Maria, recipient=João
       └─ Message #9 (resposta): parent=5, sender=João, recipient=Maria
```

Para reconstruir a conversa, o sistema:
1. Encontra a mensagem raiz subindo pela cadeia de `parent`
2. Percorre `replies` recursivamente para coletar todas as mensagens
3. Ordena tudo cronologicamente por `sentAt`

### 3.4 O Campo `status` — Ciclo de Vida de uma Conversa

| Valor | Quando é definido | Significado visual |
|---|---|---|
| `'unread'` | Padrão na criação | Mensagem nova — bolinha verde na lista |
| `'read'` | Ao abrir o modal de chat | Mensagem lida — sem indicadores especiais |
| `'replied'` | Ao enviar uma resposta | Thread com resposta recente — ativa |
| `'ignored'` | Ação manual do destinatário | Ignorada — fica cinza na lista |
| `'resolved'` | Ação manual (encerrar) | Encerrada — opacidade reduzida + badge "RESOLVIDO" |

**Comportamento importante — reativação de threads**: Quando uma nova mensagem é enviada numa thread que já foi marcada como `'resolved'`, o sistema automaticamente muda o status da raiz de volta para `'replied'`, fazendo a conversa reaparecer normalmente na lista.

### 3.5 Os Campos `contextType` e `contextId`

Permitem vincular uma mensagem a qualquer objeto do sistema — no caso de artigos/notícias:

- **`contextType`**: string `'article'` (ou qualquer nome que você definir)
- **`contextId`**: array JSON com o identificador

**Exemplo para um artigo de ID 42**:
```json
{"id": 42}
```

Esses valores aparecem na listagem de mensagens na coluna "Contexto", mostrando de onde a mensagem veio.

---

<a name="parte-4"></a>
## PARTE 4 — O `MessageRepository`

**Arquivo**: `src/Repository/MessageRepository.php`

```php
<?php
namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** Mensagens individuais não lidas — para o badge de notificação */
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

    /** Threads (raízes) recebidas pelo usuário — caixa de entrada */
    public function findInboxThreads(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.replies', 'r')
            ->where('m.parent IS NULL')
            ->andWhere('m.recipient = :user OR r.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('m.sentAt', 'DESC')
            ->distinct()
            ->getQuery()->getResult();
    }

    /** Threads enviadas pelo usuário */
    public function findSentThreads(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.parent IS NULL')
            ->andWhere('m.sender = :user')
            ->setParameter('user', $user)
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * Lista de conversas estilo WhatsApp — ordenadas pela última interação.
     * Usa CASE WHEN para ordenar pela data da última reply, ou pela raiz se não há replies.
     */
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

    /** Todas as conversas — apenas para Admin */
    public function findAllConversations(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('r')
            ->leftJoin('m.replies', 'r')
            ->where('m.parent IS NULL')
            ->groupBy('m.id')
            ->orderBy(
                'CASE WHEN MAX(r.sentAt) IS NULL THEN m.sentAt ELSE MAX(r.sentAt) END',
                'DESC'
            )
            ->getQuery()->getResult();
    }
}
```

**Por que `distinct()` no `findInboxThreads`?** O `leftJoin` com replies pode gerar linhas duplicadas — uma por resposta existente. O `distinct()` garante que cada thread apareça apenas uma vez.

**O ORDER BY com CASE WHEN**: `CASE WHEN MAX(r.sentAt) IS NULL THEN m.sentAt ELSE MAX(r.sentAt) END` significa: "Se não há respostas, use a data da mensagem raiz; caso contrário, use a data da resposta mais recente." Isso coloca conversas com atividade recente no topo, igual ao WhatsApp.


---

<a name="parte-5"></a>
## PARTE 5 — O `MessageService`

**Arquivo**: `src/Service/MessageService.php`

Centraliza toda a lógica de negócio de mensagens. Os controllers apenas chamam os métodos deste serviço.

```php
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
        private MessageRepository $messageRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    public function sendMessage(
        User $recipient,
        string $content,
        ?string $subject = null,
        ?string $contextType = null,
        ?array $contextId = null,
        ?Message $parent = null
    ): Message {
        $sender = $this->security->getUser();
        if (!$sender instanceof User) {
            throw new \LogicException('Usuário deve estar logado para enviar mensagens.');
        }

        $message = new Message();
        $message->setSender($sender);
        $message->setRecipient($recipient);
        $message->setContent($content);
        $message->setSubject($subject);
        $message->setContextType($contextType);
        $message->setContextId($contextId);
        $message->setParent($parent);

        if ($parent) {
            // Prefixo "Re:" no assunto das respostas
            $message->setSubject('Re: ' . ($parent->getSubject() ?? 'Sem assunto'));

            // Sobe até a raiz da thread
            $root = $parent;
            while ($root->getParent()) {
                $root = $root->getParent();
            }

            // Reativa threads encerradas — nova mensagem = thread ativa novamente
            if (in_array($root->getStatus(), ['resolved', 'read', 'unread'])) {
                $root->setStatus('replied');
            }

            // Marca o parent imediato como respondido
            $parent->setStatus('replied');
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    /** Marca como lida — idempotente (não sobrescreve a primeira data de leitura) */
    public function markAsRead(Message $message): void
    {
        if ($message->getReadAt() !== null) {
            return; // Já foi lida antes — preserva a data original
        }
        $message->setReadAt(new \DateTimeImmutable());
        if ($message->getStatus() === 'unread') {
            $message->setStatus('read');
        }
        $this->entityManager->flush();
    }

    public function markAsIgnored(Message $message): void
    {
        $message->setStatus('ignored');
        $this->entityManager->flush();
    }

    public function markAsResolved(Message $message): void
    {
        $message->setStatus('resolved');
        $this->entityManager->flush();
    }

    /** Retorna o número de mensagens não lidas — alimenta o badge de notificação */
    public function getUnreadCount(User $user): int
    {
        return count($this->messageRepository->findUnreadByUser($user));
    }
}
```

**Detalhes importantes do `sendMessage()`**:

1. O remetente é obtido automaticamente via `$this->security->getUser()` — não é passado como parâmetro, garantindo que nunca seja falsificado pela interface.
2. Se `$parent` não é nulo (é uma resposta):
   - O sujeito recebe o prefixo `"Re: "`
   - O sistema **sobe pela árvore de parents** até encontrar a mensagem raiz
   - O status da raiz é alterado para `'replied'` — isso reativa visualmente a conversa na lista, mesmo que ela estivesse marcada como `'resolved'`
3. O método retorna o objeto `Message` criado, permitindo que o controller saiba o `id` gerado para retornar ao frontend.

---

<a name="parte-6"></a>
## PARTE 6 — O `MessageController`

**Arquivo**: `src/Controller/Admin/MessageController.php`
**Rota base**: `/admin/message`
**Proteção global**: `#[IsGranted('ROLE_USER')]` — todos os usuários autenticados podem acessar

### 6.1 `widget()` — Badge de Notificação

```
GET /admin/message/widget  →  app_admin_message_widget
```

Renderiza apenas a bolinha de notificação. Retorna HTML parcial via sub-request (`render(controller(...))`). Chamado duas vezes no layout principal — no sidebar e no header.

```php
#[Route('/widget', name: 'app_admin_message_widget', methods: ['GET'])]
public function widget(MessageService $messageService): Response
{
    $user = $this->getUser();
    if (!$user instanceof User) {
        return new Response('');
    }
    return $this->render('admin/message/_widget.html.twig', [
        'unreadCount' => $messageService->getUnreadCount($user),
    ]);
}
```

### 6.2 `index()` — Tela Principal

```
GET /admin/message/  →  app_admin_message_index
```

```php
#[Route('/', name: 'app_admin_message_index', methods: ['GET'])]
public function index(MessageRepository $messageRepository): Response
{
    $user = $this->getUser();

    $receivedMessages = $messageRepository->findInboxThreads($user);
    $sentMessages     = $messageRepository->findSentThreads($user);
    $allMessages      = [];

    if ((int) $user->getWorkGroup() === 0) { // Admin vê tudo
        $allMessages = $messageRepository->findAllConversations();
    }

    return $this->render('admin/message/index.html.twig', [
        'receivedMessages' => $receivedMessages,
        'sentMessages'     => $sentMessages,
        'allMessages'      => $allMessages,
        'isAdmin'          => (int) $user->getWorkGroup() === 0,
    ]);
}
```

### 6.3 `read()` — Carrega uma Conversa (via AJAX)

```
GET /admin/message/{id}  →  app_admin_message_read
```

Este endpoint é chamado via `fetch()` pelo JavaScript quando o usuário clica numa conversa. Retorna o HTML parcial do modal de chat.

```php
#[Route('/{id}', name: 'app_admin_message_read', methods: ['GET'], requirements: ['id' => '\d+'])]
public function read(Message $message, MessageService $messageService): Response
{
    $user = $this->getUser();

    // Verificação de acesso: só remetente, destinatário, ou Admin
    if ($message->getRecipient() !== $user
        && $message->getSender() !== $user
        && $user->getWorkGroup() !== 0) {
        throw $this->createAccessDeniedException();
    }

    // Sobe até a raiz da thread
    $root = $message;
    while ($root->getParent()) {
        $root = $root->getParent();
    }

    // Coleta toda a thread recursivamente
    $conversation = [$root];
    $this->collectReplies($root, $conversation);

    // Ordena cronologicamente (mais antiga primeiro = topo do chat)
    usort($conversation, fn($a, $b) => $a->getSentAt() <=> $b->getSentAt());

    // Marca todas as mensagens direcionadas ao usuário atual como lidas
    foreach ($conversation as $msg) {
        if ($msg->getRecipient() === $user && $msg->getStatus() === 'unread') {
            $messageService->markAsRead($msg);
        }
    }

    return $this->render('admin/message/_read_modal.html.twig', [
        'conversation'  => $conversation,
        'rootMessage'   => $root,
    ]);
}

/** Percorre a árvore de replies recursivamente */
private function collectReplies(Message $message, array &$collection): void
{
    foreach ($message->getReplies() as $reply) {
        $collection[] = $reply;
        $this->collectReplies($reply, $collection); // Recursão para qualquer profundidade
    }
}
```

### 6.4 `send()` — Envio de Mensagem (API JSON)

```
POST /admin/message/send  →  app_admin_message_send
Content-Type: application/json
Retorna: JsonResponse
```

Único endpoint que recebe e retorna JSON — chamado via `fetch()` nos formulários embutidos nas telas de artigo.

**Payload JSON esperado**:
```json
{
  "recipient_id": 5,
  "content": "Texto do comentário",
  "subject": "Sobre o artigo XYZ",
  "context_type": "article",
  "context_id": {"id": 42}
}
```

**Normalização do `context_id`** (o campo pode chegar em vários formatos):
```php
if (is_array($contextIdInput)) {
    $contextId = $contextIdInput;
} elseif (is_string($contextIdInput)) {
    $decoded = json_decode($contextIdInput, true);
    $contextId = is_array($decoded) ? $decoded : ['id' => $contextIdInput];
} elseif (is_int($contextIdInput)) {
    $contextId = ['id' => $contextIdInput];
}
```

**Respostas**:
- Sucesso: `{"status": "success", "message_id": 42}` (HTTP 200)
- Sem destinatário ou conteúdo: `{"error": "Missing recipient or content"}` (HTTP 400)
- Destinatário não encontrado: `{"error": "Recipient not found"}` (HTTP 404)
- Erro interno: `{"error": "..."}` (HTTP 500)

### 6.5 `reply()` — Responder a uma Mensagem

```
POST /admin/message/{id}/reply  →  app_admin_message_reply
```

O destinatário da resposta é determinado automaticamente:
```php
$recipient = ($message->getSender() === $user)
    ? $message->getRecipient()  // Eu sou o remetente original → respondo para o destinatário
    : $message->getSender();    // Eu sou o destinatário → respondo para o remetente
```

### 6.6 `setStatus()` — Alterar Status Manualmente

```
POST /admin/message/{id}/status/{status}  →  app_admin_message_status
Retorna: JsonResponse
```

Permite ao destinatário ou Admin alterar o status para `'ignored'` ou `'resolved'`. Qualquer outro valor é simplesmente ignorado.

---

<a name="parte-7"></a>
## PARTE 7 — Templates do Sistema de Mensagens

### 7.1 `_widget.html.twig` — Badge de Notificação

**Arquivo**: `templates/admin/message/_widget.html.twig`

```twig
{% if unreadCount > 0 %}
    <sl-badge variant="danger" pill class="absolute top-0 right-0 translate-x-1/2 -translate-y-1/2">
        {{ unreadCount }}
    </sl-badge>
{% endif %}
```

Badge vermelho usando o componente `sl-badge` do Shoelace. As classes `translate-x-1/2 -translate-y-1/2` posicionam a bolinha no canto superior direito do elemento pai (que deve ter `position: relative`).

### 7.2 `index.html.twig` — Lista Estilo WhatsApp

**Arquivo**: `templates/admin/message/index.html.twig`

Interface de lista de conversas inspirada no WhatsApp Web. Cada item é clicável e abre o modal de chat.

#### Lógica Twig por item da lista

```twig
{% for conv in conversations %}
    {# Determina quem é "o outro" na conversa #}
    {% set contact = (conv.sender == app.user) ? conv.recipient : conv.sender %}

    {# Encontra a última mensagem da thread #}
    {% set lastMsg = conv %}
    {% set unreadCount = 0 %}

    {# Verifica a mensagem raiz #}
    {% if conv.recipient == app.user and conv.status == 'unread' %}
        {% set unreadCount = unreadCount + 1 %}
    {% endif %}

    {# Verifica as replies #}
    {% for reply in conv.replies %}
        {% if reply.sentAt > lastMsg.sentAt %}
            {% set lastMsg = reply %}
        {% endif %}
        {% if reply.recipient == app.user and reply.status == 'unread' %}
            {% set unreadCount = unreadCount + 1 %}
        {% endif %}
    {% endfor %}

    {% set isResolved = (conv.status == 'resolved') %}

    {# Item clicável que abre o modal via JS #}
    <div onclick="openMessageModal({{ conv.id }})"
         class="relative flex items-center gap-4 p-4 hover:bg-slate-50 transition-all cursor-pointer
                {{ isResolved ? 'opacity-50 grayscale bg-slate-50' : 'bg-white dark:bg-slate-800' }}">

        {# Avatar com inicial + badge de não lidos #}
        <div class="shrink-0 relative">
            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-lg">
                {{ contact.name|first }}
            </div>
            {% if unreadCount > 0 %}
                <div class="absolute -top-1 -right-1 w-5 h-5 bg-green-500 border-2 border-white rounded-full flex items-center justify-center text-[10px] text-white font-bold">
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </div>
            {% endif %}
        </div>

        {# Conteúdo: nome, prévia, hora #}
        <div class="flex-1 min-w-0">
            <div class="flex justify-between items-baseline mb-1">
                <h3 class="text-sm font-semibold text-slate-900 truncate">{{ contact.name }}</h3>
                <span class="text-xs {{ unreadCount > 0 ? 'text-green-600 font-bold' : 'text-slate-400' }} whitespace-nowrap">
                    {{ lastMsg.sentAt|date('H:i') }}
                    {% if lastMsg.sentAt|date('Y-m-d') != "now"|date('Y-m-d') %}
                        <span class="font-normal text-slate-400 ml-1">{{ lastMsg.sentAt|date('d/m') }}</span>
                    {% endif %}
                </span>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-sm truncate {{ unreadCount > 0 ? 'font-semibold text-slate-800' : 'text-slate-500' }}">
                    {% if lastMsg.sender == app.user %}
                        <sl-icon name="check" class="text-xs mr-1 opacity-60"></sl-icon>
                    {% endif %}
                    {{ lastMsg.content }}
                </p>
                {% if unreadCount > 0 %}
                    <div class="w-2.5 h-2.5 bg-green-500 rounded-full shrink-0 ml-2"></div>
                {% endif %}
            </div>
        </div>

        {# Badge RESOLVIDO #}
        {% if isResolved %}
            <div class="absolute right-4 top-4 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-600">
                RESOLVIDO
            </div>
        {% endif %}
    </div>
{% endfor %}
```

#### O Modal `<dialog>` e o JavaScript de abertura

```html
<dialog id="message-modal" class="p-0 rounded-xl shadow-2xl backdrop:bg-slate-900/40 w-full max-w-2xl">
    <div id="message-modal-content" class="min-h-[200px] flex items-center justify-center">
        <sl-spinner></sl-spinner>
    </div>
</dialog>

<script>
function openMessageModal(id) {
    const dialog  = document.getElementById('message-modal');
    const content = document.getElementById('message-modal-content');
    dialog.showModal(); // Abre o <dialog> nativo com backdrop automático

    // Mostra spinner enquanto carrega
    content.innerHTML = '<div class="p-12 flex justify-center"><sl-spinner class="text-4xl"></sl-spinner></div>';

    // Busca o HTML do modal de chat
    fetch('/admin/message/' + id)
        .then(res  => res.text())
        .then(html => { content.innerHTML = html; })
        .catch(err => { content.innerHTML = '<div class="p-6 text-red-500">Erro ao carregar.</div>'; });
}
</script>
```

### 7.3 `_read_modal.html.twig` — O Chat da Conversa

**Arquivo**: `templates/admin/message/_read_modal.html.twig`

Interface de chat com balões estilo iMessage/WhatsApp. O modal tem 3 zonas:

```
┌─────────────────────────────────────────┐
│  HEADER: assunto + contexto + botão X   │  (fixo, não rola)
├─────────────────────────────────────────┤
│                                         │
│  ÁREA DE CONVERSA com balões            │  (rola, flex-1)
│                                         │
├─────────────────────────────────────────┤
│  FOOTER: textarea + botão enviar        │  (fixo, não rola)
└─────────────────────────────────────────┘
```

```twig
<div class="p-0 flex flex-col h-[80vh] max-h-[800px]">

    {# HEADER #}
    <div class="flex items-center justify-between border-b border-slate-100 p-4 shrink-0 bg-white rounded-t-xl">
        <div>
            <h3 class="text-lg font-bold text-slate-900 line-clamp-1">{{ rootMessage.subject }}</h3>
            <div class="flex gap-2 text-xs text-slate-500 mt-0.5">
                {% if rootMessage.contextType %}
                    <span class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600 font-medium">
                        {{ rootMessage.contextType|capitalize }}
                    </span>
                {% endif %}
                <span>Iniciado em {{ rootMessage.sentAt|date('d/m/Y') }}</span>
            </div>
        </div>
        <button type="button" onclick="document.getElementById('message-modal').close()"
                class="text-slate-400 hover:text-slate-600 p-2 rounded-full hover:bg-slate-100 transition-colors">
            <sl-icon name="x-lg" label="Fechar"></sl-icon>
        </button>
    </div>

    {# ÁREA DE CONVERSA #}
    <div class="flex-1 overflow-y-auto p-4 space-y-6 bg-slate-50" id="conversation-container">
        {% for msg in conversation %}
            {% set isMe = (msg.sender == app.user) %}
            <div class="flex {{ isMe ? 'justify-end' : 'justify-start' }}">
                <div class="flex flex-col {{ isMe ? 'items-end' : 'items-start' }} max-w-[85%]">

                    {# Nome e data #}
                    <div class="flex items-baseline gap-2 mb-1 px-1">
                        <span class="text-xs font-semibold {{ isMe ? 'text-blue-900' : 'text-slate-700' }}">
                            {{ msg.sender.name }}
                        </span>
                        <span class="text-[10px] text-slate-400">{{ msg.sentAt|date('d/m/Y H:i') }}</span>
                    </div>

                    {# Balão de mensagem #}
                    <div class="relative px-4 py-3 rounded-2xl text-sm shadow-sm
                        {{ isMe
                            ? 'bg-blue-600 text-white rounded-tr-none'
                            : 'bg-white text-slate-800 border border-slate-200 rounded-tl-none' }}">
                        {{ msg.content|nl2br }}
                    </div>

                    {# Indicador de leitura (só para mensagens minhas) #}
                    {% if isMe %}
                        <div class="text-[10px] text-slate-400 mt-1 pr-1 flex items-center gap-1">
                            {% if msg.status in ['read', 'replied', 'resolved'] %}
                                <sl-icon name="check-all" class="text-blue-500 text-xs"></sl-icon> Lida
                            {% else %}
                                <sl-icon name="check" class="text-slate-400 text-xs"></sl-icon> Enviada
                            {% endif %}
                        </div>
                    {% endif %}

                </div>
            </div>
        {% endfor %}
    </div>

    {# FOOTER — Formulário de resposta #}
    <div class="border-t border-slate-200 p-4 bg-white rounded-b-xl shrink-0">
        <form action="{{ path('app_admin_message_reply', {id: conversation|last.id}) }}" method="post"
              class="flex gap-3 items-end">
            <div class="flex-1">
                <textarea name="content" rows="2"
                          class="w-full p-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 resize-none bg-slate-50 focus:bg-white transition-colors"
                          placeholder="Digite sua resposta..." required></textarea>
            </div>
            <button type="submit"
                    class="h-11 px-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition-all active:scale-95 flex items-center justify-center shrink-0">
                <sl-icon name="send" class="text-lg"></sl-icon>
            </button>
        </form>
    </div>
</div>

<script>
    // Auto-scroll para o final da conversa ao abrir
    setTimeout(() => {
        const container = document.getElementById('conversation-container');
        if (container) container.scrollTop = container.scrollHeight;
    }, 100);
</script>
```

**Sistema de cores dos balões**:
| Situação | Classe CSS | Posição |
|---|---|---|
| Mensagem minha | `bg-blue-600 text-white rounded-tr-none` | Direita |
| Mensagem do outro | `bg-white border border-slate-200 rounded-tl-none` | Esquerda |

O canto reto (`rounded-tr-none` ou `rounded-tl-none`) cria o efeito visual de "bico" do balão de chat.

**Indicadores de leitura** (abaixo do balão, só para mensagens enviadas por mim):
- `check-all` azul + "Lida" → status é `read`, `replied` ou `resolved`
- `check` cinza + "Enviada" → status ainda é `unread`


---

<a name="parte-8"></a>
## PARTE 8 — Integração do Widget no Layout (`base.html.twig`)

**Arquivo**: `templates/admin/base.html.twig`

O badge de notificação aparece em **dois lugares** no layout administrativo, usando sub-request do Symfony:

```twig
{{ render(controller('App\\Controller\\Admin\\MessageController::widget')) }}
```

Esta diretiva faz uma requisição HTTP interna — o Symfony executa a action `widget()` e injeta o HTML resultante. Isso garante que o contador seja sempre atual sem necessidade de passar variáveis pelo template inteiro.

### 8.1 No Sidebar (link "Mensagens")

```twig
<a href="{{ path('app_admin_message_index') }}" class="relative py-2 px-4 mx-4 mb-2 block rounded ...">
    <div class="flex justify-between items-center">
        <span>
            <sl-icon name="chat-dots" class="mr-2 align-text-top"></sl-icon>
            Mensagens
        </span>
        {# Badge posicionado absolutamente no canto do link #}
        {{ render(controller('App\\Controller\\Admin\\MessageController::widget')) }}
    </div>
</a>
```

### 8.2 No Header (ícone de sino)

```twig
<div class="relative">  {# position: relative obrigatório para o badge absolute funcionar #}
    <sl-icon-button name="bell" class="text-xl dark:text-white"
                    href="{{ path('app_admin_message_index') }}">
    </sl-icon-button>
    {{ render(controller('App\\Controller\\Admin\\MessageController::widget')) }}
</div>
```

### 8.3 Active State nos Links do Sidebar

Cada link verifica se a rota atual pertence ao módulo, destacando o item ativo:

```twig
{% set isMessages = app.current_route starts with 'app_admin_message' %}
{% set isArticles = app.current_route starts with 'app_admin_article' %}

<a class="py-2 px-4 mx-4 mb-2 block rounded transition-all
    {% if isMessages %}shadow bg-gradient-to-r from-white/40 via-white/90 to-white/50{% endif %}"
   href="{{ path('app_admin_message_index') }}">
    Mensagens
</a>
```

O operador `starts with` do Twig captura todas as rotas do módulo (index, show, edit, etc.).

### 8.4 Menus Condicionais por Permissão

```twig
{# Artigos: visível para quem pode editar, comentar, ou é admin #}
{% if is_granted('CAN_EDIT_ARTICLE') or is_granted('CAN_COMMENT_ARTICLE') or app.user.workGroup == 0 %}
    <a href="{{ path('app_admin_article_index') }}">Artigos</a>
{% endif %}

{# Usuários: só admin #}
{% if app.user.workGroup == 0 %}
    <a href="{{ path('app_admin_user_index') }}">Usuários</a>
{% endif %}
```

---

<a name="parte-9"></a>
## PARTE 9 — Mensagens Contextuais (Iniciadas de Dentro do Artigo)

O sistema permite que um revisor envie uma mensagem diretamente da página de um artigo, sem precisar ir até a caixa de entrada e lembrar o número do artigo.

### 9.1 No Controller — Preparação dos Destinatários

No `ArticleController::show()`, antes de renderizar a tela de detalhe, o controller prepara a lista de destinatários válidos:

```php
public function show(Article $article): Response
{
    $user = $this->getUser();

    // Verifica se o usuário atual é o autor do artigo
    $isAuthor = ($user instanceof User && $article->getAuthor() === $user);

    // Destinatário padrão: o autor do artigo
    // (Autor não pode comentar no próprio artigo — não faz sentido semântico)
    $recipients = [];
    if (!$isAuthor && $article->getAuthor()) {
        $recipients[] = $article->getAuthor();
    }

    return $this->render('admin/article/show.html.twig', [
        'article'    => $article,
        'isAuthor'   => $isAuthor,
        'recipients' => $recipients,
    ]);
}
```

### 9.2 No Template — Botão e Modal de Comentário

O botão "Adicionar Comentário" aparece apenas para revisores que **não são** o autor:

```twig
{# Condição: workGroup 2 (Revisor) ou Admin (0), E não é o autor do artigo #}
{% if (app.user.workGroup == 2 or app.user.workGroup == 0) and not isAuthor %}
    <div class="bg-blue-50/50 ring-1 ring-blue-100 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold">Discussão e Revisão</h3>
                <p class="text-xs text-slate-500">Envie comentários ou sugestões sobre este artigo.</p>
            </div>
            <button onclick="document.getElementById('send-message-modal').showModal()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium">
                <sl-icon name="chat-dots"></sl-icon>
                Adicionar Comentário
            </button>
        </div>
    </div>

    {# Modal de envio de mensagem #}
    <dialog id="send-message-modal" class="p-0 rounded-xl shadow-2xl backdrop:bg-slate-900/40 w-full max-w-lg">
        <div class="bg-white p-8">
            <h3 class="text-xl font-bold mb-6 flex items-center gap-2">
                <sl-icon name="envelope" class="text-blue-600"></sl-icon>
                Enviar Comentário
            </h3>
            <form id="send-message-form">
                {# Campos ocultos que identificam o contexto #}
                <input type="hidden" name="context_type" value="article">
                <input type="hidden" name="context_id" value="{{ article.id }}">

                <div class="space-y-5">
                    {# Destinatário #}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Destinatário</label>
                        <select name="recipient_id" class="w-full border border-slate-300 rounded-lg px-4 py-2.5" required>
                            <option value="">Selecione...</option>
                            {% for recipient in recipients %}
                                <option value="{{ recipient.id }}">{{ recipient.name }}</option>
                            {% endfor %}
                        </select>
                    </div>

                    {# Assunto (pré-preenchido com o título do artigo) #}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Assunto</label>
                        <input type="text" name="subject" value="Comentário: {{ article.title }}"
                               class="w-full border border-slate-300 rounded-lg px-4 py-2" required>
                    </div>

                    {# Mensagem #}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Mensagem</label>
                        <textarea name="content" rows="4"
                                  class="w-full border border-slate-300 rounded-lg px-4 py-3 resize-none"
                                  placeholder="Digite seu comentário..." required></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="document.getElementById('send-message-modal').close()"
                                class="px-5 py-2.5 text-slate-600 hover:bg-slate-50 rounded-lg">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                            <sl-icon name="send"></sl-icon> Enviar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        document.getElementById('send-message-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;

            // Converte o formulário para JSON
            const data = {};
            new FormData(this).forEach((value, key) => data[key] = value);

            fetch('/admin/message/send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                if (result.error) {
                    alert('Erro: ' + result.error);
                } else {
                    alert('Comentário enviado!');
                    document.getElementById('send-message-modal').close();
                    this.reset();
                }
            })
            .catch(() => alert('Erro ao enviar.'))
            .finally(() => { btn.disabled = false; });
        });
    </script>
{% endif %}
```

**Fluxo completo deste bloco**:
1. Revisor abre a página do artigo (`GET /admin/article/{id}`)
2. Vê o botão "Adicionar Comentário" (porque é workGroup 2 e não é o autor)
3. Clica → o `<dialog>` nativo abre sem reload de página
4. Preenche destinatário (o autor do artigo), assunto e mensagem
5. Clica "Enviar" → JavaScript faz `POST /admin/message/send` com JSON
6. Controller cria a `Message` no banco com `contextType='article'` e `contextId=['id'=>42]`
7. Modal fecha, formulário reseta
8. O autor verá a mensagem na próxima vez que abrir `/admin/message/`

---

<a name="parte-10"></a>
## PARTE 10 — O Sistema de Artigos/Notícias — CRUD Completo

### 10.1 A Entidade `Article` (anteriormente `Paratext`)

**Arquivo de referência**: `src/Entity/Paratext.php` (adapte para `Article.php` no seu projeto)

| Campo | Tipo Doctrine | Descrição |
|---|---|---|
| `id` | `integer` (auto-increment) | Chave primária |
| `title` | `string(255)` | Título do artigo/notícia |
| `content` | `TEXT` | Conteúdo completo em HTML (editado via TinyMCE) |
| `type` | `string(50)` | Categoria do conteúdo |
| `author` | `ManyToOne → User` nullable | Quem criou o artigo |
| `createdAt` | `DateTimeImmutable` | Data de criação (preenchida no construtor) |
| `updatedAt` | `DateTimeImmutable` nullable | Data da última edição |

**Código da entidade**:

```php
<?php
namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null; // 'news', 'article', 'report', 'opinion', etc.

    #[ORM\ManyToOne]
    private ?User $author = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ... getters e setters
}
```

**Exemplos de valores para `type`** (adapte conforme seu projeto):
| Valor | Descrição |
|---|---|
| `'news'` | Notícia de atualidade |
| `'article'` | Artigo de aprofundamento |
| `'report'` | Reportagem |
| `'opinion'` | Artigo de opinião |

### 10.2 O `ArticleController` (anteriormente `ParatextController`)

**Arquivo de referência**: `src/Controller/Admin/ParatextController.php`
**Rota base**: `/admin/article`
**Proteção global**: `#[IsGranted('ROLE_USER')]`

#### `index()` — Listagem com DataTables

```php
#[Route('/', name: 'app_admin_article_index', methods: ['GET'])]
public function index(ArticleRepository $articleRepository): Response
{
    return $this->render('admin/article/index.html.twig', [
        'articles' => $articleRepository->findAll(),
    ]);
}
```

#### `new()` — Criação

```php
#[Route('/new', name: 'app_admin_article_new', methods: ['GET', 'POST'])]
#[IsGranted('CAN_EDIT_ARTICLE')]
public function new(Request $request, EntityManagerInterface $em): Response
{
    $article = new Article();

    if ($request->isMethod('POST')) {
        $title   = $request->request->get('title');
        $content = $request->request->get('content'); // HTML do TinyMCE
        $type    = $request->request->get('type');

        if (!$title || !$type) {
            $this->addFlash('error', 'Título e Tipo são obrigatórios.');
        } else {
            $article->setTitle($title);
            $article->setContent($content);
            $article->setType($type);

            // Define o autor como o usuário logado
            $user = $this->getUser();
            if ($user instanceof User) {
                $article->setAuthor($user);
            }

            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('app_admin_article_index', [], Response::HTTP_SEE_OTHER);
        }
    }

    return $this->render('admin/article/new.html.twig', [
        'article' => $article,
    ]);
}
```

> **Atenção**: O formulário **não usa** o componente `Form` do Symfony — os campos são lidos diretamente do `Request` via `$request->request->get('campo')`. Esta abordagem é mais simples para formulários com TinyMCE.

#### `show()` — Visualização Detalhada

```php
#[Route('/{id}', name: 'app_admin_article_show', methods: ['GET'])]
public function show(Article $article, UserRepository $userRepository): Response
{
    $user     = $this->getUser();
    $isAuthor = ($user instanceof User && $article->getAuthor() === $user);

    // Destinatários para o formulário de comentário
    $recipients = [];
    if (!$isAuthor && $article->getAuthor()) {
        $recipients[] = $article->getAuthor(); // Revisor comenta com o autor
    }

    return $this->render('admin/article/show.html.twig', [
        'article'    => $article,
        'isAuthor'   => $isAuthor,
        'recipients' => $recipients,
    ]);
}
```

#### `edit()` — Edição

```php
#[Route('/{id}/edit', name: 'app_admin_article_edit', methods: ['GET', 'POST'])]
#[IsGranted('CAN_EDIT_ARTICLE')]
public function edit(Request $request, Article $article, EntityManagerInterface $em): Response
{
    if ($request->isMethod('POST')) {
        $title   = $request->request->get('title');
        $content = $request->request->get('content');
        $type    = $request->request->get('type');

        if (!$title || !$type) {
            $this->addFlash('error', 'Título e Tipo são obrigatórios.');
        } else {
            $article->setTitle($title);
            $article->setContent($content);
            $article->setType($type);
            $article->setUpdatedAt(new \DateTimeImmutable()); // Registra a edição

            $em->flush();

            return $this->redirectToRoute('app_admin_article_index', [], Response::HTTP_SEE_OTHER);
        }
    }

    return $this->render('admin/article/edit.html.twig', [
        'article' => $article,
    ]);
}
```

#### `delete()` — Exclusão com CSRF

```php
#[Route('/{id}', name: 'app_admin_article_delete', methods: ['POST'])]
#[IsGranted('CAN_EDIT_ARTICLE')]
public function delete(Request $request, Article $article, EntityManagerInterface $em): Response
{
    // Token CSRF previne deleções acidentais ou ataques via link externo
    if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
        $em->remove($article);
        $em->flush();
    }
    return $this->redirectToRoute('app_admin_article_index', [], Response::HTTP_SEE_OTHER);
}
```

### 10.3 O Formulário `_form.html.twig`

Reutilizado por `new.html.twig` e `edit.html.twig`:

```twig
<form method="post" class="space-y-6">
    <div class="grid gap-6 md:grid-cols-2">
        {# Título #}
        <div class="space-y-2">
            <label class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Título</label>
            <input type="text" name="title" value="{{ article.title }}"
                   class="w-full rounded-2xl border border-slate-200 bg-white/70 px-4 py-3 text-sm" required>
        </div>

        {# Tipo #}
        <div class="space-y-2">
            <label class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Tipo</label>
            <select name="type" class="w-full rounded-2xl border border-slate-200 bg-white/70 px-4 py-3 text-sm" required>
                <option value="">Selecione...</option>
                <option value="news"    {% if article.type == 'news'    %}selected{% endif %}>Notícia</option>
                <option value="article" {% if article.type == 'article' %}selected{% endif %}>Artigo</option>
                <option value="report"  {% if article.type == 'report'  %}selected{% endif %}>Reportagem</option>
                <option value="opinion" {% if article.type == 'opinion' %}selected{% endif %}>Opinião</option>
            </select>
        </div>
    </div>

    {# Conteúdo — editado via TinyMCE (ativado pela classe "tinymce") #}
    <div class="space-y-2">
        <label class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Conteúdo</label>
        <textarea name="content" class="tinymce">{{ article.content }}</textarea>
    </div>

    <div class="flex justify-end gap-2">
        <sl-button variant="default" outline href="{{ path('app_admin_article_index') }}">Cancelar</sl-button>
        <sl-button variant="primary" type="submit">
            <sl-icon slot="prefix" name="check2"></sl-icon>
            {{ button_label|default('Salvar') }}
        </sl-button>
    </div>
</form>
```

**Sobre o TinyMCE**: O `base.html.twig` ativa o TinyMCE para todos os elementos com a classe `tinymce`:
```javascript
tinymce.init({
    selector: '.tinymce',
    height: 300,
    menubar: false,
    plugins: 'advlist autolink lists link image charmap preview ...',
    toolbar: 'undo redo | formatselect | bold italic ...',
});
```

O conteúdo gerado pelo TinyMCE é HTML puro — deve ser armazenado em campo `TEXT` e exibido com `|raw` no Twig.

### 10.4 O Template `index.html.twig` com DataTables

```twig
{% extends 'admin/base.html.twig' %}

{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.3/css/dataTables.dataTables.min.css">
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.3/js/dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            $('#article-table').DataTable({
                pageLength: 25,
                responsive: true,
                language: {
                    search: 'Buscar:',
                    info: 'Mostrando _START_ até _END_ de _TOTAL_ registros',
                    infoEmpty: 'Nenhum registro disponível',
                    lengthMenu: 'Mostrar _MENU_ itens',
                    paginate: { first: 'Primeiro', last: 'Último', next: 'Próximo', previous: 'Anterior' },
                    zeroRecords: 'Nada encontrado'
                }
            });
        });
    </script>
{% endblock %}

{% block body %}
<div class="max-w-7xl mx-auto px-4 py-10 space-y-8">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Artigos / Notícias</h1>
            <p class="mt-2 text-sm text-slate-500">Gerencie o conteúdo editorial</p>
        </div>
        {% if is_granted('CAN_EDIT_ARTICLE') %}
            <a href="{{ path('app_admin_article_new') }}">
                <sl-button variant="primary" size="medium">
                    <sl-icon slot="prefix" name="plus-lg"></sl-icon>
                    Novo Artigo
                </sl-button>
            </a>
        {% endif %}
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg overflow-hidden">
        <div class="p-8">
            <table id="article-table" class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="py-3.5 pl-4 text-left text-xs font-semibold uppercase text-slate-500">ID</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase text-slate-500">Título</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase text-slate-500">Tipo</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase text-slate-500">Autor</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase text-slate-500">Data</th>
                        <th class="px-3 py-3.5 text-right text-xs font-semibold uppercase text-slate-500">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    {% for article in articles %}
                        <tr class="group hover:bg-slate-50 transition-colors">
                            <td class="py-4 pl-4 text-sm font-mono text-slate-500">#{{ article.id }}</td>
                            <td class="px-3 py-4 text-sm font-medium text-slate-900">{{ article.title }}</td>
                            <td class="px-3 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                    {{ article.type|capitalize }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-sm text-slate-500">{{ article.author ? article.author.name : '-' }}</td>
                            <td class="px-3 py-4 text-xs text-slate-500">{{ article.createdAt|date('d/m/Y') }}</td>
                            <td class="py-4 pr-4 text-right">
                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ path('app_admin_article_show', {id: article.id}) }}">
                                        <sl-button size="small" variant="default" circle>
                                            <sl-icon name="eye" label="Ver"></sl-icon>
                                        </sl-button>
                                    </a>
                                    {% if is_granted('CAN_EDIT_ARTICLE') %}
                                        <a href="{{ path('app_admin_article_edit', {id: article.id}) }}">
                                            <sl-button size="small" variant="default" circle>
                                                <sl-icon name="pencil" label="Editar"></sl-icon>
                                            </sl-button>
                                        </a>
                                    {% endif %}
                                </div>
                            </td>
                        </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
```

### 10.5 O Template `show.html.twig` — Visualização do Artigo

```twig
{% extends 'admin/base.html.twig' %}
{% block title %}{{ article.title }}{% endblock %}

{% block body %}
<div class="max-w-4xl mx-auto space-y-6">

    {# Breadcrumb e ações de topo #}
    <div class="flex items-center justify-between">
        <a href="{{ path('app_admin_article_index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900 flex items-center gap-2">
            <sl-icon name="arrow-left"></sl-icon> Voltar
        </a>
        {% if is_granted('CAN_EDIT_ARTICLE') %}
            <sl-button variant="primary" outline href="{{ path('app_admin_article_edit', {id: article.id}) }}">
                <sl-icon slot="prefix" name="pencil"></sl-icon>
                Editar
            </sl-button>
        {% endif %}
    </div>

    {# Card principal com o conteúdo #}
    <sl-card class="border-none bg-white/85 shadow-lg ring-1 ring-white/70 backdrop-blur">
        <div class="prose dark:prose-invert max-w-none p-6">
            {# Metadados do artigo #}
            <div class="flex items-baseline gap-4 mb-6 border-b border-slate-100 pb-4">
                <span class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ article.type }}</span>
                <span class="ml-auto text-xs text-slate-400">
                    Por {{ article.author ? article.author.name : 'Desconhecido' }}
                    em {{ article.createdAt|date('d/m/Y') }}
                    {% if article.updatedAt %}
                        · Editado em {{ article.updatedAt|date('d/m/Y') }}
                    {% endif %}
                </span>
            </div>

            <h1 class="text-3xl font-bold text-slate-900 dark:text-white mb-8">{{ article.title }}</h1>

            {# Conteúdo HTML gerado pelo TinyMCE — use |raw para renderizar o HTML #}
            <div class="font-serif text-lg leading-relaxed text-slate-800 dark:text-slate-200">
                {{ article.content|raw }}
            </div>
        </div>
    </sl-card>

    {# Bloco de comentário — visível apenas para revisores que não são o autor #}
    {% if (app.user.workGroup == 2 or app.user.workGroup == 0) and not isAuthor %}
        {# ... (veja o código completo na Parte 9) ... #}
    {% endif %}

</div>
{% endblock %}
```


---

<a name="parte-11"></a>
## PARTE 11 — Fluxo Completo: Da Criação à Aprovação

### 11.1 Diagrama do Fluxo de Revisão de Artigo

```
[Editor/Autor]                    [Revisor]                     [Editor/Autor]
     │                                │                               │
     ├─ Cria artigo                   │                               │
     │  POST /admin/article/new       │                               │
     │  (workGroup=1)                 │                               │
     │                           ─────┤                               │
     │                      GET /admin/article/{id}                   │
     │                      Lê o artigo completo                      │
     │                      Vê botão "Adicionar Comentário"           │
     │                      (workGroup=2, não é o autor)              │
     │                      Abre modal → preenche comentário          │
     │                      POST /admin/message/send (JSON)           │
     │                      context_type=article                      │
     │                      context_id={"id":42}                      │
     │                                │                               │
     ◄──────────── Badge vermelho aparece no header/sidebar ──────────┤
     │                                │                               │
     ├─ Abre /admin/message/          │                               │
     ├─ Vê conversa na lista          │                               │
     │  (com contexto "article")      │                               │
     ├─ Clica → modal de chat abre    │                               │
     ├─ Lê o comentário do revisor    │                               │
     │  (status muda para 'read')     │                               │
     ├─ Edita o artigo se necessário  │                               │
     ├─ Responde no modal de chat     │                               │
     │  POST /admin/message/{id}/reply│                               │
     │                                │                               │
     │                           ─────┤                               │
     │                      Recebe resposta                           │
     │                      Lê no modal de chat                       │
     │                      Clica "Marcar como Resolvido"             │
     │                      POST /admin/message/{id}/status/resolved  │
     │                                │                               │
     │  Conversa aparece com          │                               │
     │  opacidade e "RESOLVIDO"       │                               │
```

### 11.2 Sistema de Cores e Indicadores Visuais — Referência Completa

#### Na lista de conversas (`index.html.twig`):

| Situação | Visual |
|---|---|
| Mensagens não lidas | Linha normal · bolinha verde no avatar · ponto verde direita · hora em **verde-negrito** |
| Mensagens lidas | Linha normal · sem indicadores especiais |
| Conversa respondida | Linha normal · prévia mostra a última mensagem |
| Conversa resolvida | `opacity-50 grayscale` · badge cinza "RESOLVIDO" no canto superior direito |

#### Nos balões de chat (`_read_modal.html.twig`):

| Situação | Visual |
|---|---|
| Mensagem enviada por mim | Balão `bg-blue-600 text-white` · à direita · `rounded-tr-none` |
| Mensagem recebida | Balão `bg-white border` · à esquerda · `rounded-tl-none` |
| Enviada + já lida | `check-all` azul + texto "Lida" abaixo do balão |
| Enviada + não lida | `check` cinza + texto "Enviada" abaixo do balão |

#### Nos badges da tabela de status:

| Status | Cor | Ícone |
|---|---|---|
| `unread` | Azul (`bg-blue-50 text-blue-700`) | Ponto azul |
| `resolved` | Verde (`bg-green-50 text-green-700`) | `check` |
| `ignored` | Cinza suave (`bg-slate-50 text-slate-600`) | — |
| `read` / outros | Cinza neutro (`bg-gray-50 text-gray-600`) | — |

---

<a name="parte-12"></a>
## PARTE 12 — Guia de Replicação em Novo Projeto

Checklist completo para implementar este sistema do zero:

### Passo 1 — Criar a entidade `Article` (ou o nome do seu domínio)

```bash
php bin/console make:entity Article
```

Campos mínimos: `title`, `content` (TEXT), `type`, `author` (ManyToOne User), `createdAt`, `updatedAt`.

### Passo 2 — Criar a entidade `Message`

Copie a entidade descrita na Parte 3, adaptando o namespace.

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Passo 3 — Criar o `UserVoter`

```bash
php bin/console make:voter UserVoter
```

Adicione as constantes e a lógica do `match` descrita na Parte 2.

O Symfony registra o Voter automaticamente por auto-wiring.

### Passo 4 — Criar o `MessageService`

Crie `src/Service/MessageService.php` com o código da Parte 5. O Symfony injeta automaticamente via auto-wiring.

### Passo 5 — Criar o `MessageRepository`

Crie `src/Repository/MessageRepository.php` com os métodos da Parte 4.

### Passo 6 — Criar o `MessageController`

Crie `src/Controller/Admin/MessageController.php` com as actions da Parte 6.

Registre as rotas em `config/routes.yaml` ou via atributos PHP `#[Route(...)]`.

### Passo 7 — Criar o `ArticleController`

Crie `src/Controller/Admin/ArticleController.php` com as actions da Parte 10.

### Passo 8 — Criar os templates

Estrutura de diretórios:
```
templates/
└── admin/
    ├── base.html.twig         ← Layout com widget e sidebar
    ├── message/
    │   ├── _widget.html.twig  ← Badge de notificação
    │   ├── index.html.twig    ← Lista estilo WhatsApp
    │   └── _read_modal.html.twig ← Chat da conversa
    └── article/
        ├── index.html.twig    ← Tabela com DataTables
        ├── show.html.twig     ← Detalhe + bloco de comentário
        ├── new.html.twig      ← Formulário de criação
        ├── edit.html.twig     ← Formulário de edição
        └── _form.html.twig    ← Campos do formulário (reutilizável)
```

### Passo 9 — Configurar o Shoelace (componentes web)

No `base.html.twig`, inclua via CDN:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.19.1/cdn/themes/light.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.19.1/cdn/themes/dark.css">
<script type="module" src="https://cdn.jsdelivr.net/npm/@shoelace-style/shoelace@2.19.1/cdn/shoelace-autoloader.js"></script>
```

O autoloader carrega apenas os componentes usados na página (`sl-button`, `sl-badge`, `sl-icon`, `sl-card`, `sl-spinner`, `sl-icon-button`).

### Passo 10 — Configurar o TinyMCE

No `base.html.twig`, inclua o script e inicialize:

```html
<script src="https://cdn.tiny.cloud/1/SUA_API_KEY/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        tinymce.init({
            selector: '.tinymce',
            height: 300,
            plugins: 'advlist autolink lists link image charmap preview anchor',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter | bullist numlist'
        });
    });
</script>
```

Obtenha sua API key gratuita em [tiny.cloud](https://www.tiny.cloud).

### Passo 11 — Adicionar os links no sidebar (`base.html.twig`)

```twig
{# Link de Artigos #}
{% set isArticle = app.current_route starts with 'app_admin_article' %}
{% if is_granted('CAN_EDIT_ARTICLE') or is_granted('CAN_COMMENT_ARTICLE') or app.user.workGroup == 0 %}
    <a class="py-2 px-4 mx-4 mb-2 block rounded hover:shadow transition-all
        {% if isArticle %}shadow bg-gradient-to-r from-white/40 via-white/90 to-white/50{% endif %}"
       href="{{ path('app_admin_article_index') }}">
        <sl-icon name="newspaper" class="mr-2 align-text-top"></sl-icon>
        Artigos / Notícias
    </a>
{% endif %}

{# Link de Mensagens com badge de não lidos #}
{% set isMessage = app.current_route starts with 'app_admin_message' %}
<a class="relative py-2 px-4 mx-4 mb-2 block rounded hover:shadow transition-all
    {% if isMessage %}shadow bg-gradient-to-r from-white/40 via-white/90 to-white/50{% endif %}"
   href="{{ path('app_admin_message_index') }}">
    <div class="flex justify-between items-center">
        <span>
            <sl-icon name="chat-dots" class="mr-2 align-text-top"></sl-icon>
            Mensagens
        </span>
        {{ render(controller('App\\Controller\\Admin\\MessageController::widget')) }}
    </div>
</a>
```

### Resumo dos Arquivos a Criar

| Arquivo | Descrição |
|---|---|
| `src/Entity/User.php` | Entidade com campo `workGroup` |
| `src/Entity/Message.php` | Sistema de mensagens com threads |
| `src/Entity/Article.php` | Artigo/notícia editorial |
| `src/Repository/MessageRepository.php` | Queries de mensagens |
| `src/Repository/ArticleRepository.php` | Queries de artigos |
| `src/Security/UserVoter.php` | Regras de acesso |
| `src/Service/MessageService.php` | Lógica de negócio de mensagens |
| `src/Controller/Admin/MessageController.php` | 6 actions de mensagens |
| `src/Controller/Admin/ArticleController.php` | CRUD de artigos |
| `templates/admin/base.html.twig` | Layout com sidebar e badge |
| `templates/admin/message/_widget.html.twig` | Badge de notificação |
| `templates/admin/message/index.html.twig` | Lista WhatsApp |
| `templates/admin/message/_read_modal.html.twig` | Chat da conversa |
| `templates/admin/article/index.html.twig` | Tabela DataTables |
| `templates/admin/article/show.html.twig` | Detalhe + comentário |
| `templates/admin/article/_form.html.twig` | Formulário reutilizável |
| `migrations/` | Migration gerada pelo Doctrine |

---

*Documentação técnica baseada em implementação real com Symfony 7, Doctrine ORM, Twig, Tailwind CSS e Shoelace 2.x.*
