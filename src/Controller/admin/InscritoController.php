<?php

namespace App\Controller\admin;

use App\Repository\InscritoRepository;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/inscrito', name: 'app_admin_inscrito_')]
class InscritoController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(InscritoRepository $repo): Response
    {
        return $this->render('admin/inscrito/index.html.twig', [
            'inscritos' => $repo->findAll(),
        ]);
    }

    #[Route('/export-csv', name: 'export_csv')]
    public function exportCsv(InscritoRepository $repo): StreamedResponse
    {
        $inscritos = $repo->findAll();

        $response = new StreamedResponse(function () use ($inscritos) {
            $csv = Writer::createFromFileObject(new \SplTempFileObject());
            $csv->setDelimiter(';');

            // Header
            $csv->insertOne([
                'ID', 'Código da inscrição', 'Nome Completo', 'Nome Crachá', 'Data Nascimento',
                'E-mail', 'CPF', 'Whatsapp', 'Contato Emergência', 'Tel. Emergência',
                'Cidade', 'Estado', 'Restrição Alimentar', 'Alergia',
                'Aceite LGPD', 'Aceite Imagem',
                'Evento', 'Tipo Inscrição', 'Valor Base',
                'Itens Adicionais', 'Valor Itens', 'Valor Total',
                'Data Inscrição', 'Status',
            ]);

            foreach ($inscritos as $inscrito) {
                $itens = $inscrito->getItensAdicionais();
                $itensNomes = implode(', ', array_map(fn($i) => sprintf('%s (R$ %s)', $i->getDescricao(), number_format($i->getValor(), 2, ',', '.')), $itens->toArray()));
                $itensValores = array_reduce($itens->toArray(), fn($c, $i) => $c + (float)$i->getValor(), 0);

                $csv->insertOne([
                    $inscrito->getId(),
                    $inscrito->getInscricao()?->getId(),
                    $inscrito->getNomeCompleto(),
                    $inscrito->getNomeCracha(),
                    $inscrito->getDataNascimento()?->format('d/m/Y'),
                    $inscrito->getEmail(),
                    $inscrito->getCpf(),
                    $inscrito->getWhatsapp(),
                    $inscrito->getNomeContatoEmergencia(),
                    $inscrito->getTelefoneContatoEmergencia(),
                    $inscrito->getCidade(),
                    $inscrito->getEstado(),
                    $inscrito->getRestricaoAlimentar(),
                    $inscrito->getAlergia(),
                    $inscrito->isAceiteLgpd() ? 'Sim' : 'Não',
                    $inscrito->isAceiteImagem() ? 'Sim' : 'Não',
                    $inscrito->getInscricao()?->getEvento()?->getNome(),
                    $inscrito->getTipoInscricao()?->getNome(),
                    number_format((float)$inscrito->getTipoInscricao()?->getValorBase(), 2, ',', '.'),
                    $itensNomes,
                    number_format($itensValores, 2, ',', '.'),
                    number_format($inscrito->getValorTotal(), 2, ',', '.'),
                    $inscrito->getCreatedAt()?->format('d/m/Y H:i'),
                    $inscrito->getStatus(),
                ]);
            }

            echo $csv->toString();
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="inscritos-' . date('Ymd-His') . '.csv"');

        return $response;
    }
}
