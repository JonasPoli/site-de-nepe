<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class ErrorController extends AbstractController
{
    public function show(
        Request $request,
        FlattenException $exception,
        ?DebugLoggerInterface $logger = null,
    ): Response {
        $statusCode = $exception->getStatusCode();

        // Only show custom page for 404; let others pass through to Symfony default
        if ($statusCode === 404) {
            return $this->render('error/404.html.twig', [
                'status_code' => $statusCode,
            ], new Response('', $statusCode));
        }

        // For other errors (500, 403, etc.), render a generic error page
        return $this->render('error/generic.html.twig', [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Erro',
        ], new Response('', $statusCode));
    }
}
