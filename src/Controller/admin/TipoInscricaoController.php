<?php

namespace App\Controller\admin;

use App\Entity\TipoInscricao;
use App\Form\TipoInscricaoType;
use App\Repository\TipoInscricaoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tipo-inscricao', name: 'app_admin_tipo_inscricao_')]
class TipoInscricaoController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(TipoInscricaoRepository $repo): Response
    {
        return $this->render('admin/tipo_inscricao/index.html.twig', [
            'tipos' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $tipo = new TipoInscricao();
        $form = $this->createForm(TipoInscricaoType::class, $tipo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($tipo);
            $em->flush();
            $this->addFlash('success', 'Tipo de Inscrição criado com sucesso!');
            return $this->redirectToRoute('app_admin_tipo_inscricao_index');
        }

        return $this->render('admin/tipo_inscricao/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TipoInscricao $tipo, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TipoInscricaoType::class, $tipo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tipo de Inscrição atualizado!');
            return $this->redirectToRoute('app_admin_tipo_inscricao_index');
        }

        return $this->render('admin/tipo_inscricao/edit.html.twig', [
            'tipo' => $tipo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, TipoInscricao $tipo, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $tipo->getId(), $request->request->get('_token'))) {
            $em->remove($tipo);
            $em->flush();
            $this->addFlash('success', 'Tipo de Inscrição removido!');
        }

        return $this->redirectToRoute('app_admin_tipo_inscricao_index');
    }
}
