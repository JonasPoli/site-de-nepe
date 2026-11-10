<?php

namespace App\Tests\Security;

use App\Entity\Category;
use App\Entity\Page;
use App\Entity\PageBlock;
use App\Entity\PageSection;
use App\Entity\Tenant;
use App\Security\TenantOwnership;
use App\Tests\Support\EntityIds;
use PHPUnit\Framework\TestCase;

class TenantOwnershipTest extends TestCase
{
    use EntityIds;

    private Tenant $renovando;
    private Tenant $ueebp;

    protected function setUp(): void
    {
        $this->renovando = self::tenantWithId(3, 'renovandoconsciencias.nepebrasil.org');
        $this->ueebp = self::tenantWithId(7, 'ueebp.nepebrasil.org');
    }

    public function testSectionOfAPageBelongsToThePageTenant(): void
    {
        $section = (new PageSection())->setPage((new Page())->setTenant($this->renovando));

        $this->assertSame(3, TenantOwnership::tenantIdOf($section));
        $this->assertTrue(TenantOwnership::belongsTo($section, self::tenantWithId(3)));
        $this->assertFalse(TenantOwnership::belongsTo($section, $this->ueebp));
    }

    public function testSectionOfACategoryBelongsToTheCategoryTenant(): void
    {
        $section = (new PageSection())->setCategory((new Category())->setTenant($this->ueebp));

        $this->assertSame(7, TenantOwnership::tenantIdOf($section));
        $this->assertTrue(TenantOwnership::belongsTo($section, $this->ueebp));
        $this->assertFalse(TenantOwnership::belongsTo($section, $this->renovando));
    }

    public function testBlockFollowsTheOwnerOfItsSection(): void
    {
        $pageBlock = (new PageBlock())->setSection((new PageSection())->setPage((new Page())->setTenant($this->renovando)));
        $categoryBlock = (new PageBlock())->setSection((new PageSection())->setCategory((new Category())->setTenant($this->ueebp)));

        $this->assertSame(3, TenantOwnership::tenantIdOf($pageBlock));
        $this->assertSame(7, TenantOwnership::tenantIdOf($categoryBlock));
        $this->assertFalse(TenantOwnership::belongsTo($pageBlock, $this->ueebp), 'bloco de uma página do renovandoconsciencias no painel do ueebp');
        $this->assertTrue(TenantOwnership::belongsTo($categoryBlock, $this->ueebp));
    }

    public function testItemWithoutVisibleOwnerBelongsToNoTenant(): void
    {
        // É o que sobra quando o filtro tira da junção o dono de outro tenant
        $orphanSection = new PageSection();
        $orphanBlock = new PageBlock();

        $this->assertNull(TenantOwnership::tenantIdOf($orphanSection));
        $this->assertNull(TenantOwnership::tenantIdOf($orphanBlock));
        $this->assertFalse(TenantOwnership::belongsTo($orphanSection, $this->renovando));
        $this->assertFalse(TenantOwnership::belongsTo($orphanBlock, $this->renovando));
    }

    public function testOwnersOfDifferentTenantsBelongToNoTenant(): void
    {
        $section = (new PageSection())
            ->setPage((new Page())->setTenant($this->renovando))
            ->setCategory((new Category())->setTenant($this->ueebp));

        $this->assertNull(TenantOwnership::tenantIdOf($section));
        $this->assertFalse(TenantOwnership::belongsTo($section, $this->renovando));
    }

    public function testOwnerWithoutTenantDoesNotMatchAnUnsavedTenant(): void
    {
        // Ids nulos dos dois lados não contam como o mesmo tenant
        $this->assertFalse(TenantOwnership::belongsTo((new PageSection())->setPage(new Page()), new Tenant()));
    }
}
