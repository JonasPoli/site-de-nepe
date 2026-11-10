<?php

namespace App\Tests\Security;

use App\Entity\Message;
use App\Entity\Tenant;
use App\Entity\User;
use App\Security\MessageAccess;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;

class MessageAccessTest extends TestCase
{
    use EntityIds;

    private Tenant $alcione;
    private Tenant $ciscodedeus;
    private User $editorA;
    private User $editorB;

    protected function setUp(): void
    {
        $this->alcione = self::tenantWithId(6, 'nepealcione.nepebrasil.org');
        $this->ciscodedeus = self::tenantWithId(4, 'ciscodedeus.nepebrasil.org');
        $this->editorA = self::userWithId(30, $this->alcione, 1);
        $this->editorB = self::userWithId(31, $this->alcione, 2);
    }

    public function testParticipantsReadTheirConversation(): void
    {
        $root = $this->message($this->editorA, $this->editorB);

        $this->assertSame(MessageAccess::Allowed, MessageAccess::decide($root, $this->editorA, $this->alcione, false));
        $this->assertSame(MessageAccess::Allowed, MessageAccess::decide($root, self::userWithId(31, self::tenantWithId(6), 2), $this->alcione, false), 'mesmo usuário, outra instância');
    }

    public function testNepeAdminReadsAnyConversationOfTheNepe(): void
    {
        $root = $this->message($this->editorA, $this->editorB);

        $this->assertSame(MessageAccess::Allowed, MessageAccess::decide($root, self::userWithId(32, $this->alcione, 0), $this->alcione, true));
    }

    public function testMemberOutsideTheConversationIsForbidden(): void
    {
        $root = $this->message($this->editorA, $this->editorB);

        $this->assertSame(MessageAccess::Forbidden, MessageAccess::decide($root, self::userWithId(33, $this->alcione, 1), $this->alcione, false));
    }

    public function testConversationOfAnotherNepeIsNotFoundEvenForItsAdmin(): void
    {
        $root = $this->message($this->editorA, $this->editorB);
        $adminCisco = self::userWithId(40, $this->ciscodedeus, 0);

        $this->assertSame(MessageAccess::NotFound, MessageAccess::decide($root, $adminCisco, $this->ciscodedeus, true));
    }

    public function testParticipantOnTheWrongDomainGetsNotFound(): void
    {
        $root = $this->message($this->editorA, $this->editorB);

        $this->assertSame(MessageAccess::NotFound, MessageAccess::decide($root, $this->editorA, $this->ciscodedeus, false));
    }

    public function testRepliesFollowTheInitialMessage(): void
    {
        $root = $this->message($this->editorA, $this->editorB);
        $reply = $this->message($this->editorB, $this->editorA)->setParent($root);

        $this->assertSame(MessageAccess::Allowed, MessageAccess::decide($reply, $this->editorA, $this->alcione, false));
        $this->assertSame(MessageAccess::NotFound, MessageAccess::decide($reply, self::userWithId(40, $this->ciscodedeus, 0), $this->ciscodedeus, true));
    }

    public function testConversationWithTheSuperAdminBelongsToTheNepeOfTheOtherMember(): void
    {
        $superAdmin = self::userWithId(1, null, 0);
        $root = $this->message($superAdmin, $this->editorA);

        $this->assertTrue(MessageAccess::belongsTo($root, $this->alcione));
        $this->assertFalse(MessageAccess::belongsTo($root, $this->ciscodedeus));
        $this->assertSame(MessageAccess::Allowed, MessageAccess::decide($root, $this->editorA, $this->alcione, false));
    }

    private function message(User $sender, User $recipient): Message
    {
        return (new Message())->setSender($sender)->setRecipient($recipient)->setContent('Olá');
    }
}
