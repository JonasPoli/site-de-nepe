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
            'footerVideos'      => $this->videoRepository->findBy(['status' => \App\Entity\Enum\ArticleStatus::Published], ['createdAt' => 'DESC'], 5),
            'footerStudies'     => $this->studyRepository->findBy(['status' => \App\Entity\Enum\ArticleStatus::Published], ['createdAt' => 'DESC'], 5),
            'hasStudies'        => $this->studyRepository->count(['status' => \App\Entity\Enum\ArticleStatus::Published]) > 0,
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

        // A cor que o NEPE escolhe é pensada para preenchimento, não para letra.
        // `--color-*-text` é a mesma cor escurecida (ou clareada) só o quanto baste para
        // chegar a 4,6:1 sobre o fundo da página, mantendo matiz e saturação; e
        // `--color-on-*` é a tinta que fica legível POR CIMA do preenchimento — preto ou
        // branco, pelo contraste. O botão do banner usava #1a1c2e fixo e dava 4,07:1.
        $fundoDaPagina = '#f8f9fc';
        $primaryText = ColorContrast::legivelSobre($primary, $fundoDaPagina, 4.6) ?? $primary;
        $secondaryText = ColorContrast::legivelSobre($secondary, $fundoDaPagina, 4.6) ?? $secondary;
        // Preenchimento sólido: há cor em que nem preto nem branco chegam a 4,5:1
        // (o rosa #cb4e9a dá 4,22 com preto e 4,12 com branco). Nesses casos o próprio
        // fundo cede um pouco, mantendo matiz e saturação.
        [$primarySolid, $onPrimary] = ColorContrast::parFundoTinta($primary);
        [$secondarySolid, $onSecondary] = ColorContrast::parFundoTinta($secondary);

        return <<<HTML
            <style>
            :root {
              --color-primary: {$primary};
              --color-secondary: {$secondary};
              --color-primary-rgb: {$this->hexToRgb($primary)};
              --color-secondary-rgb: {$this->hexToRgb($secondary)};
              --color-primary-text: {$primaryText};
              --color-secondary-text: {$secondaryText};
              --color-primary-solid: {$primarySolid};
              --color-secondary-solid: {$secondarySolid};
              --color-on-primary: {$onPrimary};
              --color-on-secondary: {$onSecondary};
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
