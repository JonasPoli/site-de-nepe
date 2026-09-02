<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:biblia:import',
    description: 'Popula ou atualiza a base de dados bíblica (versão ARC) a partir do arquivo compactado.',
    aliases: ['app:import-biblia', 'biblia:import']
)]
class ImportBibliaCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Caminho do arquivo de dados compactado', $this->projectDir . '/data/biblia_arc.json.gz')
            ->setHelp('Este comando lê o arquivo data/biblia_arc.json.gz e realiza o upsert idempotente dos testamentos, versões, livros, versículos externos e versículos da ARC.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getOption('file');

        if (!file_exists($filePath)) {
            $io->error("Arquivo de dados não encontrado: $filePath");
            return Command::FAILURE;
        }

        $io->title('📖 Importação de Dados Bíblicos (ARC)');
        $io->text("Lendo e descompactando arquivo: <info>$filePath</info> (" . round(filesize($filePath) / 1024 / 1024, 2) . " MB)...");

        $startTime = microtime(true);
        $compressed = file_get_contents($filePath);
        if ($compressed === false) {
            $io->error('Falha ao ler o arquivo compactado.');
            return Command::FAILURE;
        }

        $json = @gzdecode($compressed);
        if ($json === false) {
            $io->error('Falha ao descompactar os dados com gzdecode.');
            return Command::FAILURE;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $io->error('Falha ao decodificar JSON dos dados bíblicos.');
            return Command::FAILURE;
        }

        unset($compressed, $json);

        // 1. Testamentos
        $testaments = $data['testaments'] ?? [];
        $io->section(sprintf('1. Importando Testamentos (%d registros)...', count($testaments)));
        foreach ($testaments as $t) {
            $this->connection->executeStatement(
                'INSERT INTO biblia_testament (id, name) VALUES (:id, :name)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)',
                ['id' => (int) $t['id'], 'name' => (string) $t['name']]
            );
        }
        $io->writeln(' <info>✔ Testamentos atualizados.</info>');

        // 2. Versões
        $versions = $data['versions'] ?? [];
        $io->section(sprintf('2. Importando Versões (%d registros)...', count($versions)));
        foreach ($versions as $v) {
            $this->connection->executeStatement(
                'INSERT INTO biblia_version (id, name, bible_com_abreviation, abbreviation)
                 VALUES (:id, :name, :bible_com, :abbrev)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    bible_com_abreviation = VALUES(bible_com_abreviation),
                    abbreviation = VALUES(abbreviation)',
                [
                    'id' => (int) $v['id'],
                    'name' => (string) $v['name'],
                    'bible_com' => $v['bible_com_abreviation'] ?? null,
                    'abbrev' => $v['abbreviation'] ?? null,
                ]
            );
        }
        $io->writeln(' <info>✔ Versões atualizadas.</info>');

        // 3. Livros
        $books = $data['books'] ?? [];
        $io->section(sprintf('3. Importando Livros (%d registros)...', count($books)));
        foreach ($books as $b) {
            $this->connection->executeStatement(
                'INSERT INTO biblia_book (id, testment_id, position, name, abbreviation, bible_com_abreviation, human_long)
                 VALUES (:id, :testment_id, :pos, :name, :abbrev, :bible_com, :human_long)
                 ON DUPLICATE KEY UPDATE
                    testment_id = VALUES(testment_id),
                    position = VALUES(position),
                    name = VALUES(name),
                    abbreviation = VALUES(abbreviation),
                    bible_com_abreviation = VALUES(bible_com_abreviation),
                    human_long = VALUES(human_long)',
                [
                    'id' => (int) $b['id'],
                    'testment_id' => (int) $b['testment_id'],
                    'pos' => (int) $b['position'],
                    'name' => (string) $b['name'],
                    'abbrev' => (string) $b['abbreviation'],
                    'bible_com' => $b['bible_com_abreviation'] ?? null,
                    'human_long' => $b['human_long'] ?? null,
                ]
            );
        }
        $io->writeln(' <info>✔ Livros atualizados.</info>');

        // 4. Versículos Externos (Batch de 1000)
        $verseExt = $data['verse_ext'] ?? [];
        $totalExt = count($verseExt);
        $io->section(sprintf('4. Importando Versículos Externos (%d registros)...', $totalExt));
        $this->importVerseExtBatch($verseExt, $output);

        // 5. Versículos ARC (Batch de 1000)
        $verses = $data['verses'] ?? [];
        $totalVerses = count($verses);
        $io->section(sprintf('5. Importando Versículos ARC (%d registros)...', $totalVerses));
        $this->importVersesBatch($verses, $output);

        $elapsed = round(microtime(true) - $startTime, 2);

        $io->success(sprintf(
            "Importação bíblica concluída com sucesso em %s segundos!\n- %d Testamentos\n- %d Versões\n- %d Livros\n- %d Versículos Externos\n- %d Versículos ARC",
            $elapsed,
            count($testaments),
            count($versions),
            count($books),
            $totalExt,
            $totalVerses
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function importVerseExtBatch(array $items, OutputInterface $output): void
    {
        $chunks = array_chunk($items, 1000);
        $progress = new ProgressBar($output, count($items));
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $progress->start();

        foreach ($chunks as $chunk) {
            $values = [];
            $params = [];
            $i = 0;

            foreach ($chunk as $row) {
                $values[] = "(:id_$i, :book_$i, :chap_$i, :ver_$i, :yr_$i, :yd_$i, :pl_$i, :tr_$i)";
                $params["id_$i"] = (int) $row['id'];
                $params["book_$i"] = (int) $row['book_id'];
                $params["chap_$i"] = (int) $row['chapter'];
                $params["ver_$i"] = (int) $row['verse'];
                $params["yr_$i"] = isset($row['year']) && $row['year'] !== '' ? (int) $row['year'] : null;
                $params["yd_$i"] = $row['year_description'] ?? null;
                $params["pl_$i"] = $row['place'] ?? null;
                $params["tr_$i"] = (int) ($row['translated'] ?? 0);
                $i++;
            }

            $sql = 'INSERT INTO biblia_verse_ext (id, book_id, chapter, verse, year, year_description, place, translated) VALUES '
                . implode(', ', $values)
                . ' ON DUPLICATE KEY UPDATE
                    book_id = VALUES(book_id),
                    chapter = VALUES(chapter),
                    verse = VALUES(verse),
                    year = VALUES(year),
                    year_description = VALUES(year_description),
                    place = VALUES(place),
                    translated = VALUES(translated)';

            $this->connection->executeStatement($sql, $params);
            $progress->advance(count($chunk));
        }

        $progress->finish();
        $output->writeln("\n <info>✔ Versículos externos importados.</info>");
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function importVersesBatch(array $items, OutputInterface $output): void
    {
        $chunks = array_chunk($items, 1000);
        $progress = new ProgressBar($output, count($items));
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $progress->start();

        foreach ($chunks as $chunk) {
            $values = [];
            $params = [];
            $i = 0;

            foreach ($chunk as $row) {
                $values[] = "(:id_$i, :ver_id_$i, :book_$i, :chap_$i, :ver_$i, :txt_$i, :ext_$i, :sub_$i)";
                $params["id_$i"] = (int) $row['id'];
                $params["ver_id_$i"] = (int) $row['version_id'];
                $params["book_$i"] = (int) $row['book_id'];
                $params["chap_$i"] = (int) $row['chapter'];
                $params["ver_$i"] = (int) $row['verse'];
                $params["txt_$i"] = (string) $row['text'];
                $params["ext_$i"] = isset($row['external_id_id']) && $row['external_id_id'] !== '' ? (int) $row['external_id_id'] : null;
                $params["sub_$i"] = $row['subject'] ?? null;
                $i++;
            }

            $sql = 'INSERT INTO biblia_verse (id, version_id, book_id, chapter, verse, text, external_id_id, subject) VALUES '
                . implode(', ', $values)
                . ' ON DUPLICATE KEY UPDATE
                    version_id = VALUES(version_id),
                    book_id = VALUES(book_id),
                    chapter = VALUES(chapter),
                    verse = VALUES(verse),
                    text = VALUES(text),
                    external_id_id = VALUES(external_id_id),
                    subject = VALUES(subject)';

            $this->connection->executeStatement($sql, $params);
            $progress->advance(count($chunk));
        }

        $progress->finish();
        $output->writeln("\n <info>✔ Versículos ARC importados.</info>");
    }
}
