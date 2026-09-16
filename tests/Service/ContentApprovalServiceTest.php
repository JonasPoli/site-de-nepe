<?php

namespace App\Tests\Service;

use App\Entity\Enum\ArticleStatus;
use App\Entity\Study;
use App\Entity\StudyApproval;
use App\Entity\StudyMaterial;
use App\Entity\Tenant;
use App\Entity\User;
use App\Entity\VideoSupport;
use App\Service\ApprovalResult;
use App\Service\ContentApprovalService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ContentApprovalServiceTest extends TestCase
{
    private Tenant $tenant;
    private User $author;
    private ContentApprovalService $service;

    protected function setUp(): void
    {
        $this->tenant = (new Tenant())->setRequiredApprovals(2);
        $this->author = $this->member($this->tenant);
        $this->service = new ContentApprovalService($this->createMock(EntityManagerInterface::class));
    }

    public function testSubmitOnlyMovesDraftsToPending(): void
    {
        $study = $this->study();

        $this->assertTrue($this->service->submit($study));
        $this->assertSame(ArticleStatus::Pending, $study->getStatus());
        $this->assertFalse($this->service->submit($study));
    }

    public function testPublishesOnlyWhenTheTenantRequiredApprovalsAreReached(): void
    {
        $study = $this->pendingStudy();

        $this->assertSame(ApprovalResult::Approved, $this->service->approve($study, $this->member($this->tenant)));
        $this->assertSame(ArticleStatus::Pending, $study->getStatus());
        $this->assertNull($study->getPublishedAt());

        $this->assertSame(ApprovalResult::Published, $this->service->approve($study, $this->member($this->tenant), 'Revisado'));
        $this->assertTrue($study->isPublished());
        $this->assertNotNull($study->getPublishedAt());
        $this->assertSame(2, $study->getApprovalCount());
        $this->assertInstanceOf(StudyApproval::class, $study->getApprovals()->first());
    }

    public function testRejectsApprovalsThatDontCount(): void
    {
        $study = $this->pendingStudy();
        $reviewer = $this->member($this->tenant);

        $this->assertSame(ApprovalResult::OwnContent, $this->service->approve($study, $this->author));
        $this->assertSame(ApprovalResult::OtherTenant, $this->service->approve($study, $this->member(new Tenant())));
        $this->assertSame(ApprovalResult::Approved, $this->service->approve($study, $reviewer));
        $this->assertSame(ApprovalResult::AlreadyApproved, $this->service->approve($study, $reviewer));
        $this->assertSame(ApprovalResult::NotPending, $this->service->approve($this->study(), $reviewer));
        $this->assertSame(1, $study->getApprovalCount());
    }

    public function testSuperAdminCanApproveContentOfAnyTenant(): void
    {
        $superAdmin = new User(); // no tenant + workGroup 0

        $this->assertSame(ApprovalResult::Approved, $this->service->approve($this->pendingStudy(), $superAdmin));
    }

    public function testChangingPublishedContentSendsItBackToDraft(): void
    {
        $video = (new VideoSupport())->setTenant($this->tenant)->setAuthor($this->author)->setTitle('Vídeo')->setYoutubeId('abc123');
        $this->service->submit($video);
        $this->service->approve($video, $this->member($this->tenant));
        $this->service->approve($video, $this->member($this->tenant));
        $this->assertTrue($video->isPublished());

        $fingerprint = $this->service->fingerprint($video);
        $this->assertFalse($this->service->invalidateIfChanged($video, $fingerprint), 'unchanged content keeps its approvals');

        $video->setBibliaChapter(10);

        $this->assertTrue($this->service->invalidateIfChanged($video, $fingerprint));
        $this->assertSame(ArticleStatus::Draft, $video->getStatus());
        $this->assertNull($video->getPublishedAt());
        $this->assertSame(0, $video->getApprovalCount());
    }

    public function testChangingTheAttachedFilesSendsPublishedContentBackToDraft(): void
    {
        $study = $this->pendingStudy();
        $study->getMaterials()->add($this->material($study, 'Apostila'));
        $this->service->approve($study, $this->member($this->tenant));
        $this->service->approve($study, $this->member($this->tenant));
        $this->assertTrue($study->isPublished());

        $fingerprint = $this->service->fingerprint($study);
        $study->getMaterials()->add($this->material($study, 'Slides'));

        $this->assertTrue($this->service->invalidateIfChanged($study, $fingerprint));
        $this->assertSame(ArticleStatus::Draft, $study->getStatus());
        $this->assertSame(0, $study->getApprovalCount());
    }

    public function testRemovingAnAttachedFileAlsoInvalidatesTheApprovals(): void
    {
        $study = $this->pendingStudy();
        $material = $this->material($study, 'Apostila');
        $study->getMaterials()->add($material);
        $this->service->approve($study, $this->member($this->tenant));
        $this->service->approve($study, $this->member($this->tenant));

        $fingerprint = $this->service->fingerprint($study);
        $study->getMaterials()->removeElement($material);

        $this->assertTrue($this->service->invalidateIfChanged($study, $fingerprint));
        $this->assertSame(ArticleStatus::Draft, $study->getStatus());
    }

    private function material(Study $study, string $label): StudyMaterial
    {
        return (new StudyMaterial())->setStudy($study)->setLabel($label)->setExtension('pdf')->setFilename($label . '.pdf');
    }

    public function testChangingADraftWithoutApprovalsKeepsIt(): void
    {
        $study = $this->study();
        $fingerprint = $this->service->fingerprint($study);

        $study->setTitle('Outro título');

        $this->assertFalse($this->service->invalidateIfChanged($study, $fingerprint));
        $this->assertSame(ArticleStatus::Draft, $study->getStatus());
    }

    private function member(Tenant $tenant): User
    {
        return (new User())->setTenant($tenant)->setWorkGroup(2);
    }

    private function study(): Study
    {
        return (new Study())->setTenant($this->tenant)->setAuthor($this->author)->setTitle('O bom samaritano');
    }

    private function pendingStudy(): Study
    {
        $study = $this->study();
        $this->service->submit($study);

        return $study;
    }
}
