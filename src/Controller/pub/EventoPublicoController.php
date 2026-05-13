<?php

namespace App\Controller\pub;

use App\Entity\Inscricao;
use App\Entity\Inscrito;
use App\Repository\EventoRepository;
use App\Repository\ItemAdicionalRepository;
use App\Repository\TipoInscricaoRepository;
use App\Service\PixService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/e', name: 'app_pub_evento_')]
class EventoPublicoController extends AbstractController
{
    /**
     * Public event page: shows event info + current session inscritos
     */
    #[Route('/{token}', name: 'show')]
    public function show(string $token, EventoRepository $eventoRepo, Request $request): Response
    {
        $evento = $eventoRepo->findOneBy(['token' => $token]);
        if (!$evento) {
            throw $this->createNotFoundException('Evento não encontrado.');
        }

        $session = $request->getSession();
        $inscritos = $session->get('inscritos_' . $token, []);

        return $this->render('pub/evento/show.html.twig', [
            'evento'    => $evento,
            'inscritos' => $inscritos,
        ]);
    }

    /**
     * Shows the form to add a new inscrito
     */
    #[Route('/{token}/adicionar', name: 'adicionar', methods: ['GET', 'POST'])]
    public function adicionar(
        string $token,
        EventoRepository $eventoRepo,
        TipoInscricaoRepository $tipoRepo,
        ItemAdicionalRepository $itemRepo,
        Request $request
    ): Response {
        $evento = $eventoRepo->findOneBy(['token' => $token]);
        if (!$evento) {
            throw $this->createNotFoundException('Evento não encontrado.');
        }

        $tipos = $tipoRepo->findBy(['evento' => $evento, 'status' => 'ativo']);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Validate required checkboxes
            if (empty($data['aceite_lgpd']) || empty($data['aceite_imagem'])) {
                $this->addFlash('danger', 'Você deve aceitar os termos obrigatórios.');
            } else {
                $tipoId = (int)($data['tipo_inscricao'] ?? 0);
                $tipo = $tipoRepo->find($tipoId);

                $itensIds = $data['itens_adicionais'] ?? [];
                $itens = [];
                foreach ($itensIds as $iId) {
                    $item = $itemRepo->find((int)$iId);
                    if ($item && $item->getTipoInscricao()->getId() === $tipoId) {
                        $itens[] = [
                            'id'       => $item->getId(),
                            'descricao'=> $item->getDescricao(),
                            'valor'    => (float) $item->getValor(),
                        ];
                    }
                }

                $valorTotal = ($tipo ? (float)$tipo->getValorBase() : 0)
                    + array_sum(array_column($itens, 'valor'));

                $inscritoData = [
                    'nomeCompleto'              => $data['nome_completo'] ?? '',
                    'nomeCracha'                => $data['nome_cracha'] ?? '',
                    'dataNascimento'            => $data['data_nascimento'] ?? '',
                    'email'                     => $data['email'] ?? '',
                    'cpf'                       => $data['cpf'] ?? '',
                    'whatsapp'                  => $data['whatsapp'] ?? '',
                    'nomeContatoEmergencia'     => $data['nome_contato_emergencia'] ?? '',
                    'telefoneContatoEmergencia' => $data['telefone_contato_emergencia'] ?? '',
                    'cidade'                    => $data['cidade'] ?? '',
                    'estado'                    => $data['estado'] ?? '',
                    'restricaoAlimentar'        => $data['restricao_alimentar'] ?? '',
                    'alergia'                   => $data['alergia'] ?? '',
                    'tipoId'                    => $tipoId,
                    'tipoNome'                  => $tipo?->getNome() ?? '',
                    'valorBase'                 => $tipo ? (float)$tipo->getValorBase() : 0,
                    'itensAdicionais'           => $itens,
                    'valorTotal'                => $valorTotal,
                ];

                $session = $request->getSession();
                $inscritos = $session->get('inscritos_' . $token, []);
                $inscritos[] = $inscritoData;
                $session->set('inscritos_' . $token, $inscritos);

                return $this->redirectToRoute('app_pub_evento_show', ['token' => $token]);
            }
        }

        return $this->render('pub/evento/adicionar.html.twig', [
            'evento' => $evento,
            'tipos'  => $tipos,
        ]);
    }

    /**
     * Returns items for a given TipoInscricao (AJAX)
     */
    #[Route('/api/tipo/{id}/itens', name: 'api_itens')]
    public function apiItens(int $id, TipoInscricaoRepository $tipoRepo): JsonResponse
    {
        $tipo = $tipoRepo->find($id);
        if (!$tipo) {
            return new JsonResponse([]);
        }

        $itens = $tipo->getItensAdicionais()->filter(fn($i) => $i->getStatus() === 'ativo');
        $data = [];
        foreach ($itens as $item) {
            $data[] = [
                'id'       => $item->getId(),
                'descricao'=> $item->getDescricao(),
                'valor'    => (float) $item->getValor(),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Remove a specific inscrito from session
     */
    #[Route('/{token}/remover/{index}', name: 'remover', methods: ['POST'])]
    public function remover(string $token, int $index, Request $request): Response
    {
        $session = $request->getSession();
        $inscritos = $session->get('inscritos_' . $token, []);
        if (isset($inscritos[$index])) {
            array_splice($inscritos, $index, 1);
            $session->set('inscritos_' . $token, $inscritos);
        }
        return $this->redirectToRoute('app_pub_evento_show', ['token' => $token]);
    }

    /**
     * Finalizar: saves to DB immediately and redirects to finalizado
     */
    #[Route('/{token}/finalizar', name: 'finalizar')]
    public function finalizar(
        string $token,
        EventoRepository $eventoRepo,
        TipoInscricaoRepository $tipoRepo,
        ItemAdicionalRepository $itemRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $evento = $eventoRepo->findOneBy(['token' => $token]);
        if (!$evento) {
            throw $this->createNotFoundException('Evento não encontrado.');
        }

        $session = $request->getSession();
        $inscritosData = $session->get('inscritos_' . $token, []);

        if (empty($inscritosData)) {
            return $this->redirectToRoute('app_pub_evento_show', ['token' => $token]);
        }

        // SAVE TO DATABASE IMMEDIATELY
        $inscricao = new Inscricao();
        $inscricao->setEvento($evento);
        $inscricao->setNomeCadastrante($inscritosData[0]['nomeCompleto'] ?? 'Visitante');
        $em->persist($inscricao);

        foreach ($inscritosData as $iData) {
            $inscrito = new Inscrito();
            $inscrito->setNomeCompleto($iData['nomeCompleto']);
            $inscrito->setNomeCracha($iData['nomeCracha'] ?? null);
            if (!empty($iData['dataNascimento'])) {
                try {
                    $inscrito->setDataNascimento(new \DateTime($iData['dataNascimento']));
                } catch (\Exception $e) {}
            }
            $inscrito->setEmail($iData['email'] ?? null);
            $inscrito->setCpf($iData['cpf'] ?? null);
            $inscrito->setWhatsapp($iData['whatsapp'] ?? null);
            $inscrito->setNomeContatoEmergencia($iData['nomeContatoEmergencia'] ?? null);
            $inscrito->setTelefoneContatoEmergencia($iData['telefoneContatoEmergencia'] ?? null);
            $inscrito->setCidade($iData['cidade'] ?? null);
            $inscrito->setEstado($iData['estado'] ?? null);
            $inscrito->setRestricaoAlimentar($iData['restricaoAlimentar'] ?? null);
            $inscrito->setAlergia($iData['alergia'] ?? null);
            $inscrito->setAceiteLgpd(true);
            $inscrito->setAceiteImagem(true);
            $inscrito->setInscricao($inscricao);

            $tipo = $tipoRepo->find($iData['tipoId']);
            if ($tipo) {
                $inscrito->setTipoInscricao($tipo);
            }

            foreach ($iData['itensAdicionais'] as $itemData) {
                $item = $itemRepo->find($itemData['id']);
                if ($item) {
                    $inscrito->addItensAdicionais($item);
                }
            }

            $em->persist($inscrito);
        }

        $em->flush();

        // CLEAR SESSION
        $session->remove('inscritos_' . $token);

        return $this->redirectToRoute('app_pub_evento_finalizado', [
            'token' => $token,
            'id'    => $inscricao->getId()
        ]);
    }

    /**
     * Finalizado: persistent success page with payment info
     */
    #[Route('/{token}/finalizado/{id}', name: 'finalizado')]
    public function finalizado(
        string $token,
        int $id,
        EventoRepository $eventoRepo,
        EntityManagerInterface $em,
        PixService $pixService
    ): Response {
        $evento = $eventoRepo->findOneBy(['token' => $token]);
        $inscricao = $em->getRepository(Inscricao::class)->find($id);

        if (!$evento || !$inscricao || $inscricao->getEvento() !== $evento) {
            throw $this->createNotFoundException();
        }

        $valorTotal = 0;
        foreach ($inscricao->getInscritos() as $inscrito) {
            $valorTotal += $inscrito->getTipoInscricao()?->getValorBase() ?? 0;
            foreach ($inscrito->getItensAdicionais() as $item) {
                $valorTotal += $item->getValor();
            }
        }

        $txid = 'EVT' . $evento->getId() . 'T' . $inscricao->getId();
        $pixCode = $pixService->generatePixCode(
            $evento->getChavePix(),
            $evento->getBeneficiarioPix() ?? $evento->getNome(),
            $evento->getCidadePix() ?? 'BRASIL',
            $valorTotal,
            $txid
        );
        $qrCode = $pixService->generateQrCode($pixCode);

        return $this->render('pub/evento/finalizar.html.twig', [
            'evento'     => $evento,
            'inscricao'  => $inscricao,
            'valorTotal' => $valorTotal,
            'pixCode'    => $pixCode,
            'qrCode'     => $qrCode,
        ]);
    }


}
