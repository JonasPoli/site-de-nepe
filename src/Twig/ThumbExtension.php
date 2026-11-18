<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\ImageFormats;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Miniaturas das imagens enviadas pelo painel, no tamanho em que aparecem na tela.
 *
 *   {{ 'uploads/page_block/x.png'|thumb('bloco_imagem') }}        → URL da miniatura
 *   {{ 'uploads/hero/x.png'|thumb_attrs('banner_desktop') }}      → src, width e height prontos
 *   {{ 'uploads/hero/x.png'|thumb_srcset({'banner_mobile': 768}) }}
 *
 * Quando o servidor não consegue gerar a miniatura (formato que o GD não lê), devolve o
 * arquivo original, para a página não quebrar.
 *
 * O caminho pronto é devolvido direto: o `getBrowserPath()` do LiipImagine devolveria
 * `/media/cache/resolve/…`, que responde 302 antes de servir a imagem.
 */
final class ThumbExtension extends AbstractExtension
{
    /** @var array<string, bool> */
    private array $podeGerar = [];

    /** @var array<string, array{0: int, 1: int}|null> */
    private array $dimensoes = [];

    public function __construct(
        private readonly CacheManager $cacheManager,
        /** @var array<string, array<string, mixed>> os conjuntos de filtro do liip_imagine.yaml */
        #[Autowire('%liip_imagine.filter_sets%')]
        private readonly array $conjuntosDeFiltro,
        private readonly Packages $packages,
        private readonly ImageFormats $imageFormats,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('thumb', $this->url(...)),
            new TwigFilter('thumb_attrs', $this->atributos(...), ['is_safe' => ['html']]),
            new TwigFilter('thumb_srcset', $this->srcset(...)),
        ];
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('thumb_size', $this->tamanho(...)),
        ];
    }

    /** URL da miniatura, ou do arquivo original quando o servidor não converte o formato. */
    public function url(?string $caminho, string $filtro): string
    {
        $caminho = ltrim((string) $caminho, '/');
        if ($caminho === '') {
            return '';
        }

        if (!$this->geravel($caminho)) {
            return $this->packages->getUrl($caminho);
        }

        // Miniatura já gerada: aponta direto para o arquivo. O `getBrowserPath` do
        // LiipImagine devolveria `/media/cache/resolve/…`, que responde 302 antes de
        // servir a imagem — justamente a cadeia de redirecionamento que derruba o LCP.
        // O `app:miniaturas:aquecer` gera tudo depois do deploy para este caminho valer.
        $pronta = $this->caminhoDaMiniatura($caminho, $filtro);
        if ($pronta !== null && is_file($this->absoluto($pronta))) {
            return $this->packages->getUrl($pronta);
        }

        return $this->cacheManager->getBrowserPath($caminho, $filtro);
    }

    /**
     * Onde o LiipImagine grava a miniatura deste filtro: `media/cache/<filtro>/<caminho>`
     * com a extensão trocada pelo formato do filtro (regra do FormatExtensionResolver).
     */
    private function caminhoDaMiniatura(string $caminho, string $filtro): ?string
    {
        $config = $this->conjuntosDeFiltro[$filtro] ?? null;
        if ($config === null) {
            return null;
        }

        $formato = $config['format'] ?? null;
        $destino = $caminho;
        if (is_string($formato) && $formato !== '') {
            $semExtensao = preg_replace('~\.[^./]+$~', '', $caminho) ?? $caminho;
            $destino = $semExtensao . '.' . $formato;
        }

        return 'media/cache/' . $filtro . '/' . $destino;
    }

    /**
     * `src="…" width="…" height="…"`, com as medidas da miniatura que vai ser servida,
     * para o navegador reservar o espaço e não haver deslocamento de layout.
     */
    public function atributos(?string $caminho, string $filtro): string
    {
        $url = $this->url($caminho, $filtro);
        if ($url === '') {
            return '';
        }

        [$largura, $altura] = $this->tamanho($caminho, $filtro);
        if ($largura <= 0 || $altura <= 0) {
            return sprintf('src="%s"', htmlspecialchars($url, \ENT_QUOTES));
        }

        return sprintf(
            'src="%s" width="%d" height="%d"',
            htmlspecialchars($url, \ENT_QUOTES),
            $largura,
            $altura,
        );
    }

    /**
     * `srcset` com um tamanho por filtro, para o navegador baixar só o que cabe na tela.
     *
     * @param array<string, int> $filtrosPorLargura filtro => largura declarada no `w`
     */
    public function srcset(?string $caminho, array $filtrosPorLargura): string
    {
        $partes = [];
        foreach ($filtrosPorLargura as $filtro => $largura) {
            $url = $this->url($caminho, $filtro);
            if ($url !== '') {
                $partes[] = $url . ' ' . $largura . 'w';
            }
        }

        return implode(', ', $partes);
    }

    /**
     * Medidas que a miniatura terá: a proporção do arquivo original encaixada no tamanho
     * do filtro. Sem conseguir ler o original, devolve o tamanho do filtro.
     *
     * @return array{0: int, 1: int}
     */
    public function tamanho(?string $caminho, string $filtro): array
    {
        $alvo = $this->tamanhoDoFiltro($filtro);
        $original = $this->tamanhoOriginal(ltrim((string) $caminho, '/'));

        if ($alvo === null) {
            return $original ?? [0, 0];
        }
        if ($original === null) {
            return $alvo;
        }

        [$lo, $ao] = $original;
        [$la, $aa] = $alvo;
        if ($lo <= 0 || $ao <= 0) {
            return $alvo;
        }

        if ($this->modoDoFiltro($filtro) === 'outbound') {
            // Recorte: sai exatamente no tamanho pedido, sem passar do original
            return [min($la, $lo), min($aa, $ao)];
        }

        // inset: cabe inteira dentro da caixa, mantendo a proporção e sem ampliar
        $escala = min($la / $lo, $aa / $ao, 1.0);

        return [max(1, (int) round($lo * $escala)), max(1, (int) round($ao * $escala))];
    }

    private function geravel(string $caminho): bool
    {
        return $this->podeGerar[$caminho] ??= $this->imageFormats->canThumbnail($this->absoluto($caminho));
    }

    /** @return array{0: int, 1: int}|null */
    private function tamanhoOriginal(string $caminho): ?array
    {
        if ($caminho === '') {
            return null;
        }
        if (array_key_exists($caminho, $this->dimensoes)) {
            return $this->dimensoes[$caminho];
        }

        $absoluto = $this->absoluto($caminho);
        $info = is_file($absoluto) ? @getimagesize($absoluto) : false;

        return $this->dimensoes[$caminho] = $info === false ? null : [(int) $info[0], (int) $info[1]];
    }

    /** @return array{0: int, 1: int}|null */
    private function tamanhoDoFiltro(string $filtro): ?array
    {
        $tamanho = $this->conjuntosDeFiltro[$filtro]['filters']['thumbnail']['size'] ?? null;

        return is_array($tamanho) && count($tamanho) === 2
            ? [(int) $tamanho[0], (int) $tamanho[1]]
            : null;
    }

    private function modoDoFiltro(string $filtro): string
    {
        return (string) ($this->conjuntosDeFiltro[$filtro]['filters']['thumbnail']['mode'] ?? 'inset');
    }

    private function absoluto(string $caminho): string
    {
        return $this->projectDir . '/public/' . $caminho;
    }
}
