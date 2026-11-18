<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Category;
use App\Entity\Page;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Decide, no servidor, quais arquivos a página precisa carregar.
 *
 * Hoje só o carrossel: ele é a única biblioteca pesada que sobrou, e a maioria das páginas
 * não tem carrossel nenhum. Carregá-lo só onde é usado tira CSS e JavaScript do caminho de
 * renderização das demais páginas.
 *
 * Ver a requirement "Biblioteca carregada só onde é usada" da spec `desempenho-das-paginas`.
 */
final class AtivosExtension extends AbstractExtension
{
    /**
     * Só o bloco de banner usa o Swiper, e apenas com mais de um banner — galeria,
     * depoimentos e logos de parceiros têm JavaScript próprio no tema.
     */
    private const BLOCOS_COM_CARROSSEL = [];

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('usa_carrossel', $this->usaCarrossel(...)),
            new TwigFunction('imagem_do_lcp', $this->imagemDoLcp(...)),
        ];
    }

    /** @param array<string, mixed> $contexto o `_context` do template */
    public function usaCarrossel(array $contexto = []): bool
    {
        // O slider do topo da home tem JavaScript próprio no tema; o Swiper só entra
        // pelo bloco de banner, e apenas com mais de um banner.
        foreach (['page', 'category'] as $chave) {
            $alvo = $contexto[$chave] ?? null;
            if (($alvo instanceof Page || $alvo instanceof Category) && $this->temBlocoDeCarrossel($alvo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * O arquivo da imagem que provavelmente forma o maior elemento visível da página,
     * para o `<head>` pré-carregá-la.
     *
     * Sem isto, o navegador só descobre a imagem depois de ler o HTML até o bloco, e o LCP
     * atrasa — era o que segurava o desempenho entre 96 e 99 nos sites com banner no topo.
     * A ordem segue a do próprio template: banner do topo, fundo da primeira seção e, por
     * último, a primeira imagem de conteúdo.
     *
     * @param array<string, mixed> $contexto o `_context` do template
     */
    public function imagemDoLcp(array $contexto = []): ?string
    {
        $banners = $contexto['banners'] ?? null;
        if (is_iterable($banners)) {
            foreach ($banners as $banner) {
                if (method_exists($banner, 'getBackgroundImage') && $banner->getBackgroundImage()) {
                    return 'uploads/hero/' . $banner->getBackgroundImage();
                }
            }
        }

        foreach (['page', 'category'] as $chave) {
            $alvo = $contexto[$chave] ?? null;
            if (!$alvo instanceof Page && !$alvo instanceof Category) {
                continue;
            }

            foreach ($alvo->getSections() as $secao) {
                if (method_exists($secao, 'isActive') && !$secao->isActive()) {
                    continue;
                }

                foreach ($secao->getBlocks() as $bloco) {
                    $tipo = $bloco->getType();
                    $tipo = $tipo instanceof \BackedEnum ? (string) $tipo->value : (string) $tipo;
                    if ($tipo !== 'banner') {
                        continue;
                    }

                    // O bloco de banner guarda os banners no JSON de configuração; só quando
                    // essa lista está vazia é que o template cai na imagem do próprio bloco.
                    $config = $bloco->getConfig() ?? [];
                    foreach ($config['banners'] ?? [] as $banner) {
                        if (($banner['active'] ?? '1') === '1' && !empty($banner['image'])) {
                            return 'uploads/page_block/' . $banner['image'];
                        }
                    }

                    if ($bloco->getImage()) {
                        return 'uploads/page_block/' . $bloco->getImage();
                    }
                }

                if ($secao->getBgType() === 'image' && $secao->getBgImage()) {
                    return 'uploads/section/bg/' . $secao->getBgImage();
                }

                foreach ($secao->getBlocks() as $bloco) {
                    if ($bloco->getImage()) {
                        return 'uploads/page_block/' . $bloco->getImage();
                    }
                }

                // Só a primeira seção conta: o que vem depois não é o maior elemento visível.
                break;
            }
        }

        return null;
    }

    private function temBlocoDeCarrossel(Page|Category $alvo): bool
    {
        foreach ($alvo->getSections() as $secao) {
            if (method_exists($secao, 'isActive') && !$secao->isActive()) {
                continue;
            }
            foreach ($secao->getBlocks() as $bloco) {
                $tipo = $bloco->getType();
                $tipo = $tipo instanceof \BackedEnum ? (string) $tipo->value : (string) $tipo;

                // Um banner só não vira carrossel: o template desenha a imagem direto.
                // Carregar o Swiper nesse caso custava 4,5 KB de CSS bloqueante e 40 KB
                // de JavaScript à toa — e banner único é o caso da maioria das páginas.
                if ($tipo === 'banner') {
                    if ($this->quantosBanners($bloco) > 1) {
                        return true;
                    }
                    continue;
                }

                if (in_array($tipo, self::BLOCOS_COM_CARROSSEL, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Quantos banners ativos o bloco tem; sem lista na configuração, vale a imagem do bloco. */
    private function quantosBanners(object $bloco): int
    {
        $config = method_exists($bloco, 'getConfig') ? ($bloco->getConfig() ?? []) : [];
        $lista = $config['banners'] ?? null;

        if (is_array($lista) && $lista !== []) {
            $ativos = 0;
            foreach ($lista as $banner) {
                if (($banner['active'] ?? '1') === '1') {
                    ++$ativos;
                }
            }

            return $ativos;
        }

        return $bloco->getTitle() ? 1 : 0;
    }
}
