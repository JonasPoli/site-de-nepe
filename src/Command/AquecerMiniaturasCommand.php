<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImageFormats;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * Gera as miniaturas de todas as imagens enviadas pelo painel.
 *
 * Por que existe: enquanto a miniatura não está gravada, o LiipImagine devolve no HTML a
 * URL `/media/cache/resolve/…`, que responde 302 e só então serve a imagem — a cadeia de
 * redirecionamento que derruba o LCP. O `build.sh` apaga `public/media/cache/*` a cada
 * deploy, então este comando roda logo depois dele, antes de qualquer medição.
 *
 *   php bin/console app:miniaturas:aquecer            # gera o que falta
 *   php bin/console app:miniaturas:aquecer --refazer  # regera tudo
 *   php bin/console app:miniaturas:aquecer --simular  # só lista o que faria
 */
#[AsCommand(
    name: 'app:miniaturas:aquecer',
    description: 'Gera as miniaturas (LiipImagine) de todas as imagens enviadas pelo painel.',
)]
final class AquecerMiniaturasCommand extends Command
{
    /** Pasta de upload => filtros que os temas usam para as imagens dela. */
    /** Pasta de upload => filtros que os temas usam para as imagens dela. */
    private const FILTROS_POR_PASTA = [
        'uploads/hero' => ['hero_desktop', 'hero_mobile'],
        'uploads/page_block' => ['bloco_imagem', 'hero_desktop', 'hero_mobile'],
        'uploads/study_cover' => ['study_thumb', 'study_featured', 'study_cover'],
        'uploads/article' => ['news_thumb', 'study_featured'],
        'uploads/article_gallery' => ['galeria'],
        'uploads/video_thumb' => ['news_thumb', 'study_featured'],
        'uploads/tenant/logo' => ['logo_header', 'logo_favicon'],
        'uploads/tenant/about' => ['conteudo_largo'],
        'uploads/page_cover' => ['capa_pagina'],
        'uploads/page_block_gallery' => ['galeria'],
        'uploads/testimonial_avatar' => ['avatar'],
    ];

    public function __construct(
        private readonly DataManager $dataManager,
        private readonly FilterManager $filterManager,
        private readonly CacheManager $cacheManager,
        private readonly ImageFormats $imageFormats,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('refazer', null, InputOption::VALUE_NONE, 'Regera as miniaturas que já existem.')
            ->addOption('simular', null, InputOption::VALUE_NONE, 'Só mostra o que seria gerado.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $refazer = (bool) $input->getOption('refazer');
        $simular = (bool) $input->getOption('simular');

        $geradas = 0;
        $puladas = 0;
        $semConversao = 0;
        $erros = [];

        foreach (self::FILTROS_POR_PASTA as $pasta => $filtros) {
            $absoluta = $this->projectDir . '/public/' . $pasta;
            if (!is_dir($absoluta)) {
                continue;
            }

            $arquivos = (new Finder())->files()->in($absoluta)->depth('== 0');
            foreach ($arquivos as $arquivo) {
                $relativo = $pasta . '/' . $arquivo->getFilename();

                if (!$this->imageFormats->canThumbnail($arquivo->getPathname())) {
                    ++$semConversao;
                    continue;
                }

                foreach ($filtros as $filtro) {
                    if (!$refazer && $this->cacheManager->isStored($relativo, $filtro)) {
                        ++$puladas;
                        continue;
                    }
                    if ($simular) {
                        $io->writeln(sprintf('  geraria <info>%s</info> em %s', $relativo, $filtro));
                        ++$geradas;
                        continue;
                    }

                    try {
                        $binario = $this->dataManager->find($filtro, $relativo);
                        $this->cacheManager->store(
                            $this->filterManager->applyFilter($binario, $filtro),
                            $relativo,
                            $filtro,
                        );
                        ++$geradas;
                    } catch (\Throwable $e) {
                        $erros[] = sprintf('%s (%s): %s', $relativo, $filtro, $e->getMessage());
                    }
                }
            }
        }

        $io->success(sprintf(
            '%d miniatura(s) %s, %d já existia(m), %d imagem(ns) que o servidor não converte.',
            $geradas,
            $simular ? 'seriam geradas' : 'gerada(s)',
            $puladas,
            $semConversao,
        ));

        if ($erros !== []) {
            $io->warning(count($erros) . ' falha(s):');
            $io->listing(array_slice($erros, 0, 20));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
