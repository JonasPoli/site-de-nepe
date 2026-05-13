<?php

namespace App\Controller\admin;

use App\Repository\EventoRepository;
use App\Repository\InscritoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashController extends AbstractController
{
    #[Route('/', name: 'admin_dash')]
    public function dashboard(EventoRepository $eventoRepo, InscritoRepository $inscritoRepo): Response
    {
        $eventosCount = $eventoRepo->count([]);
        
        $inscritos = $inscritoRepo->findAll();
        $totalInscritos = count($inscritos);
        
        $receitaGeral = 0;
        $receitaHoje = 0;
        $inscritosHoje = 0;
        
        $hoje = (new \DateTime())->format('Y-m-d');
        
        foreach ($inscritos as $inscrito) {
            $valor = $inscrito->getValorTotal();
            $receitaGeral += $valor;
            
            if ($inscrito->getCreatedAt() && $inscrito->getCreatedAt()->format('Y-m-d') === $hoje) {
                $receitaHoje += $valor;
                $inscritosHoje++;
            }
        }
        
        $ultimosEventos = $eventoRepo->findBy([], ['dataInicio' => 'DESC'], 5);

        return $this->render('admin/dash/dashboard.html.twig', [
            'total_eventos' => $eventosCount,
            'total_inscritos' => $totalInscritos,
            'receita_geral' => $receitaGeral,
            'receita_hoje' => $receitaHoje,
            'inscritos_hoje' => $inscritosHoje,
            'ultimos_eventos' => $ultimosEventos
        ]);
    }
}
