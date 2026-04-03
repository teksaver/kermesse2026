<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LogsController extends AbstractController
{
    #[Route('/index.php/admin/logs', name: 'app_admin_logs', methods: ['GET'])]
    #[Route('/admin/logs', name: 'app_admin_logs_pretty', methods: ['GET'])]
    public function index(
        Request $request,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        #[Autowire('%env(default::LOG_VIEWER_TOKEN)%')] ?string $logViewerToken,
    ): Response {
        $providedToken = (string) ($request->query->get('token') ?? $request->headers->get('x-log-viewer-token') ?? '');

        if (null === $logViewerToken || '' === trim($logViewerToken)) {
            return $this->render('admin/logs.html.twig', [
                'authorized' => false,
                'message' => 'Le visualiseur de logs n est pas configure. Ajoutez LOG_VIEWER_TOKEN en production.',
                'logs' => [],
                'lineCount' => 150,
            ], new Response('', Response::HTTP_SERVICE_UNAVAILABLE));
        }

        if ('' === $providedToken || !hash_equals($logViewerToken, $providedToken)) {
            return $this->render('admin/logs.html.twig', [
                'authorized' => false,
                'message' => 'Acces refuse. Ajoutez ?token=... avec la valeur de LOG_VIEWER_TOKEN.',
                'logs' => [],
                'lineCount' => 150,
            ], new Response('', Response::HTTP_FORBIDDEN));
        }

        $lineCount = max(20, min(500, (int) $request->query->get('lines', 150)));
        $logPaths = [
            'Symfony prod' => $projectDir.'/var/log/prod.log',
            'Symfony dev' => $projectDir.'/var/log/dev.log',
            'PHP error_log (project)' => $projectDir.'/error_log',
            'PHP error_log (httpdocs)' => $projectDir.'/httpdocs/error_log',
        ];

        $logs = [];
        foreach ($logPaths as $label => $path) {
            $logs[] = [
                'label' => $label,
                'path' => $path,
                'exists' => is_file($path),
                'tail' => is_file($path) ? $this->tailFile($path, $lineCount) : '',
            ];
        }

        return $this->render('admin/logs.html.twig', [
            'authorized' => true,
            'message' => null,
            'logs' => $logs,
            'lineCount' => $lineCount,
        ]);
    }

    private function tailFile(string $path, int $lines): string
    {
        $content = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($content)) {
            return '';
        }

        return implode("\n", array_slice($content, -$lines));
    }
}

