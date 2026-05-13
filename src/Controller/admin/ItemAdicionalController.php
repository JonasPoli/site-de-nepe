<?php

namespace App\Controller\admin;

use App\Entity\ItemAdicional;
use App\Form\ItemAdicionalType;
use App\Repository\ItemAdicionalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/item-adicional', name: 'app_admin_item_adicional_')]
class ItemAdicionalController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ItemAdicionalRepository $repo): Response
    {
        return $this->render('admin/item_adicional/index.html.twig', [
            'itens' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new ItemAdicional();
        $form = $this->createForm(ItemAdicionalType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Item Adicional criado com sucesso!');
            return $this->redirectToRoute('app_admin_item_adicional_index');
        }

        return $this->render('admin/item_adicional/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ItemAdicional $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ItemAdicionalType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Item Adicional atualizado!');
            return $this->redirectToRoute('app_admin_item_adicional_index');
        }

        return $this->render('admin/item_adicional/edit.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, ItemAdicional $item, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $item->getId(), $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Item Adicional removido!');
        }

        return $this->redirectToRoute('app_admin_item_adicional_index');
    }
}
