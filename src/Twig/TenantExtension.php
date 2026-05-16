<?php

namespace App\Twig;

use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\StudyRepository;
use App\Repository\VideoSupportRepository;
use App\Service\TenantContext;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Exposes `tenant()` as a global Twig function and injects footer data
 * (footerPages, footerCategories, footerVideos) as globals so all templates
 * can render the 4-column footer without explicit controller injection.
 */
class TenantExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PageRepository $pageRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly VideoSupportRepository $videoRepository,
        private readonly StudyRepository $studyRepository,
    ) {}

    #[\Override]
    public function getGlobals(): array
    {
        return [
            'currentTenant'     => $this->tenantContext->getTenant(),
            'headerCategories'  => $this->categoryRepository->findForHeader(),
            'footerPages'       => $this->pageRepository->findForFooter(),
            'footerCategories'  => $this->categoryRepository->findForFooter(),
            'footerVideos'      => $this->videoRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'footerStudies'     => $this->studyRepository->findBy([], ['createdAt' => 'DESC'], 5),
        ];
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant', $this->getTenant(...)),
            new TwigFunction('tenant_css_vars', $this->getCssVars(...), ['is_safe' => ['html']]),
        ];
    }

    public function getTenant(): ?\App\Entity\Tenant
    {
        return $this->tenantContext->getTenant();
    }

    /**
     * Returns an inline <style> block with CSS custom properties for the tenant's colors.
     * Call in the <head> of every public base template.
     */
    public function getCssVars(): string
    {
        $tenant = $this->tenantContext->getTenant();
        if ($tenant === null) {
            return '';
        }

        $primary = htmlspecialchars($tenant->getPrimaryColor() ?? '#0044cc');
        $secondary = htmlspecialchars($tenant->getSecondaryColor() ?? '#ffaa00');

        return <<<HTML
            <style>
            :root {
              --color-primary: {$primary};
              --color-secondary: {$secondary};
              --color-primary-rgb: {$this->hexToRgb($primary)};
              --color-secondary-rgb: {$this->hexToRgb($secondary)};
            }
            </style>
            HTML;
    }

    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "{$r}, {$g}, {$b}";
    }
}
