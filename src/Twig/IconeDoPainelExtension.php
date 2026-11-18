<?php

declare(strict_types=1);

namespace App\Twig;

use Psr\Log\LoggerInterface;
use Symfony\UX\Icons\IconRendererInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Desenha, como SVG embutido, o ícone que o cliente escolheu no painel e que fica gravado
 * no banco como classe de CSS (`fa-solid fa-building`, `fa-brands fa-whatsapp`, `bi bi-star`).
 *
 *   {{ it.icon|icone_do_painel('cetec-blurb-icon') }}
 *
 * Por que existe: desenhar esses ícones com fonte custava, nas páginas públicas, o CSS do
 * FontAwesome e o do Bootstrap Icons, vindos de dois CDNs, mais os arquivos de fonte —
 * cerca de 110 KB bloqueando a renderização para desenhar menos de vinte ícones por site.
 *
 * Ícone que não está gravado no repositório não é desenhado (o layout não quebra) e fica
 * registrado no log; `app:icones:sincronizar` baixa os que faltam.
 *
 * Ver a spec `desempenho-das-paginas` da change seo-e-performance-dos-sites.
 */
final class IconeDoPainelExtension extends AbstractExtension
{
    /** Prefixo da classe do FontAwesome => coleção do Iconify. */
    private const COLECOES_FA = [
        'fa-solid' => 'fa6-solid',
        'fas' => 'fa6-solid',
        'fa-regular' => 'fa6-regular',
        'far' => 'fa6-regular',
        'fa-brands' => 'fa6-brands',
        'fab' => 'fa6-brands',
    ];

    /**
     * Nomes do FontAwesome 5 que o painel ainda tem gravados e que mudaram no 6.
     * Sem isto, o ícone some na página porque o Iconify só conhece o nome novo.
     */
    private const RENOMEADOS_FA5 = [
        'file-alt' => 'file-lines',
        'money-check-alt' => 'money-check-dollar',
        'external-link-alt' => 'up-right-from-square',
        'sign-in-alt' => 'right-to-bracket',
        'sign-out-alt' => 'right-from-bracket',
        'tachometer-alt' => 'gauge-high',
        'trash-alt' => 'trash-can',
        'edit' => 'pen-to-square',
        'search' => 'magnifying-glass',
        'map-marker-alt' => 'location-dot',
        'phone-alt' => 'phone',
        'shopping-cart' => 'cart-shopping',
        'home' => 'house',
    ];

    public function __construct(
        private readonly IconRendererInterface $renderer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('icone_do_painel', $this->desenhar(...), ['is_safe' => ['html']]),
            new TwigFilter('nome_do_icone', $this->nome(...)),
        ];
    }

    public function desenhar(?string $classes, string $classeCss = '', string $tamanho = '1em'): string
    {
        $nome = $this->nome($classes);
        if ($nome === null) {
            return '';
        }

        $atributos = ['width' => $tamanho, 'height' => $tamanho, 'aria-hidden' => 'true'];
        if ($classeCss !== '') {
            $atributos['class'] = $classeCss;
        }

        try {
            return $this->renderer->renderIcon($nome, $atributos);
        } catch (\Throwable $e) {
            $this->logger->warning('Ícone do painel não encontrado: {icone} ({erro}). Rode app:icones:sincronizar.', [
                'icone' => $nome,
                'erro' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Converte a classe gravada no nome do Iconify.
     *
     *   'fa-solid fa-building'  => 'fa6-solid:building'
     *   'fab fa-whatsapp'       => 'fa6-brands:whatsapp'
     *   'bi bi-chevron-right'   => 'bi:chevron-right'
     *   'fa-building'           => 'fa6-solid:building'   (sem estilo, assume sólido)
     *   'book-half'             => 'bi:book-half'         (nome solto do Shoelace)
     */
    public function nome(?string $classes): ?string
    {
        $classes = trim((string) $classes);
        if ($classes === '') {
            return null;
        }

        // Já veio no formato do Iconify
        if (str_contains($classes, ':')) {
            return $classes;
        }

        $partes = preg_split('~\s+~', $classes) ?: [];

        // Nome solto, sem prefixo: é o nome do Shoelace, cuja biblioteca padrão
        // é o Bootstrap Icons (ex.: 'book-half', 'telephone-fill').
        if (count($partes) === 1 && !str_contains($partes[0], '-') === false
            && !str_starts_with($partes[0], 'fa') && !str_starts_with($partes[0], 'bi')) {
            return 'bi:' . $partes[0];
        }
        $colecao = null;
        $glifo = null;

        foreach ($partes as $parte) {
            if (isset(self::COLECOES_FA[$parte])) {
                $colecao = self::COLECOES_FA[$parte];
                continue;
            }
            if ($parte === 'bi') {
                $colecao = 'bi';
                continue;
            }
            if (str_starts_with($parte, 'bi-')) {
                $colecao ??= 'bi';
                $glifo ??= substr($parte, 3);
                continue;
            }
            if (str_starts_with($parte, 'fa-') && !isset(self::COLECOES_FA[$parte])) {
                $glifo ??= substr($parte, 3);
            }
        }

        if ($glifo === null || $glifo === '') {
            return null;
        }

        // Sem estilo declarado, o FontAwesome desenha o sólido
        $colecao ??= 'fa6-solid';

        if (str_starts_with($colecao, 'fa6-')) {
            $glifo = self::RENOMEADOS_FA5[$glifo] ?? $glifo;
        }

        return $colecao . ':' . $glifo;
    }
}
