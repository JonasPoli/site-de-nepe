<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\VideoSupportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Traz para o nosso domínio a capa dos vídeos que não têm imagem própria.
 *
 * Por que existe: sem imagem própria, o tema apontava direto para
 * `img.youtube.com/vi/<id>/maxresdefault.jpg` — 1280x720, cerca de 150 KB cada, num
 * cartão de menos de 400 px, servido por um domínio de terceiro com cache de 2 horas.
 * Numa home com dois vídeos eram 267 KB. Guardando a capa aqui, ela passa pelo filtro de
 * miniatura como qualquer outra imagem: WebP, no tamanho do cartão e com o nosso cache.
 *
 *   php bin/console app:videos:capas            # baixa o que falta
 *   php bin/console app:videos:capas --refazer  # baixa de novo
 *
 * O comando não altera o banco: grava o arquivo com o nome `yt-<id>.jpg`, e o filtro
 * Twig `capa_do_video` usa esse arquivo quando ele existe.
 */
#[AsCommand(
    name: 'app:videos:capas',
    description: 'Baixa para uploads/video_thumb a capa dos vídeos do YouTube sem imagem própria.',
)]
final class BaixarCapasDeVideoCommand extends Command
{
    /** Em ordem: a melhor primeiro. Nem todo vídeo tem a maxresdefault. */
    private const TAMANHOS = ['maxresdefault', 'hq720', 'hqdefault'];

    public function __construct(
        private readonly VideoSupportRepository $videos,
        private readonly HttpClientInterface $http,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('refazer', null, InputOption::VALUE_NONE, 'Baixa de novo as capas que já existem.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $refazer = (bool) $input->getOption('refazer');

        $pasta = $this->projectDir . '/public/uploads/video_thumb';
        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            $io->error('Não consegui criar public/uploads/video_thumb.');

            return Command::FAILURE;
        }

        $baixadas = 0;
        $jaTinham = 0;
        $semCapa = [];

        foreach ($this->videos->findAll() as $video) {
            $id = $video->getYoutubeId();
            if ($id === '' || $video->hasCustomThumbnail()) {
                continue;
            }

            $destino = $pasta . '/yt-' . $id . '.jpg';
            if (!$refazer && is_file($destino)) {
                ++$jaTinham;
                continue;
            }

            $binario = $this->baixar($id);
            if ($binario === null) {
                $semCapa[] = $id;
                continue;
            }

            file_put_contents($destino, $binario);
            ++$baixadas;
            $io->writeln(sprintf('  <info>%s</info> (%d KB)', basename($destino), (int) (strlen($binario) / 1024)));
        }

        $io->success(sprintf('%d capa(s) baixada(s), %d já existia(m).', $baixadas, $jaTinham));

        if ($semCapa !== []) {
            $io->warning('Sem capa no YouTube (seguem apontando para lá): ' . implode(', ', $semCapa));
        }

        return Command::SUCCESS;
    }

    private function baixar(string $youtubeId): ?string
    {
        foreach (self::TAMANHOS as $tamanho) {
            try {
                $resposta = $this->http->request(
                    'GET',
                    sprintf('https://img.youtube.com/vi/%s/%s.jpg', rawurlencode($youtubeId), $tamanho),
                    ['timeout' => 20],
                );

                // O YouTube devolve 200 com uma imagem cinza de 120x90 quando o tamanho
                // pedido não existe; o tamanho do arquivo denuncia.
                if ($resposta->getStatusCode() === 200) {
                    $conteudo = $resposta->getContent();
                    if (strlen($conteudo) > 2048) {
                        return $conteudo;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
