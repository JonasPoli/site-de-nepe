<?php

namespace App\Controller\admin;

use App\Entity\Evento;
use App\Form\EventoType;
use App\Repository\EventoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evento', name: 'app_admin_evento_')]
class EventoController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(EventoRepository $repo): Response
    {
        return $this->render('admin/evento/index.html.twig', [
            'eventos' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $evento = new Evento();
        $form = $this->createForm(EventoType::class, $evento);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($evento);
            $em->flush();
            $this->addFlash('success', 'Evento criado com sucesso!');
            return $this->redirectToRoute('app_admin_evento_index');
        }

        return $this->render('admin/evento/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evento $evento, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EventoType::class, $evento);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Evento atualizado com sucesso!');
            return $this->redirectToRoute('app_admin_evento_index');
        }

        return $this->render('admin/evento/edit.html.twig', [
            'evento' => $evento,
            'form'   => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Evento $evento, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $evento->getId(), $request->request->get('_token'))) {
            $em->remove($evento);
            $em->flush();
            $this->addFlash('success', 'Evento removido com sucesso!');
        }

        return $this->redirectToRoute('app_admin_evento_index');
    }
}
