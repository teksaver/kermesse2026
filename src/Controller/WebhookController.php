<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    #[Route('/webhook/deploy', name: 'webhook_deploy', methods: ['POST', 'GET'])]
    public function deploy(
        Request $request,
        KernelInterface $kernel,
        #[Autowire('%env(WEBHOOK_SECRET)%')] string $secret
    ): JsonResponse {
        $suppliedSecret = $request->query->get('secret') ?? $request->request->get('secret');

        if (!$secret || $suppliedSecret !== $secret) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $projectDir = $kernel->getProjectDir();
        $archivePath = $projectDir.'/deploy-package.zip';

        if (!is_file($archivePath)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Deployment archive not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!class_exists(\ZipArchive::class)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'ZipArchive extension is not available on the server.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $temporaryDir = $projectDir.'/var/deploy-extract';

        try {
            $this->removeDirectory($temporaryDir);
            if (!is_dir(dirname($temporaryDir))) {
                mkdir(dirname($temporaryDir), 0775, true);
            }
            mkdir($temporaryDir, 0775, true);

            $zip = new \ZipArchive();
            if (true !== $zip->open($archivePath)) {
                throw new \RuntimeException('Unable to open deployment archive.');
            }

            if (!$zip->extractTo($temporaryDir)) {
                $zip->close();
                throw new \RuntimeException('Unable to extract deployment archive.');
            }

            $zip->close();

            $this->copyDirectoryContents($temporaryDir, $projectDir);

            @unlink($archivePath);
            $this->removeDirectory($temporaryDir);
        } catch (\Throwable $e) {
            $this->removeDirectory($temporaryDir);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->runMigrations($request, $kernel, $secret);
    }

    #[Route('/webhook/migrations', name: 'webhook_migrations', methods: ['POST', 'GET'])]
    public function migrate(
        Request $request,
        KernelInterface $kernel,
        #[Autowire('%env(WEBHOOK_SECRET)%')] string $secret
    ): JsonResponse {
        $suppliedSecret = $request->query->get('secret') ?? $request->request->get('secret');

        if (!$secret || $suppliedSecret !== $secret) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->runMigrations($request, $kernel, $secret);
    }

    private function runMigrations(Request $request, KernelInterface $kernel, string $secret): JsonResponse
    {
        $suppliedSecret = $request->query->get('secret') ?? $request->request->get('secret');

        if (!$secret || $suppliedSecret !== $secret) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $results = [];

        $cacheClear = $this->runConsoleCommand($kernel, [
            'command' => 'cache:clear',
            '--env' => 'prod',
            '--no-warmup' => true,
        ]);
        $results['cache:clear'] = $cacheClear['output'];

        if (0 !== $cacheClear['exitCode']) {
            return new JsonResponse([
                'success' => false,
                'step' => 'cache:clear',
                'output' => $results,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $cacheWarmup = $this->runConsoleCommand($kernel, [
            'command' => 'cache:warmup',
            '--env' => 'prod',
        ]);
        $results['cache:warmup'] = $cacheWarmup['output'];

        if (0 !== $cacheWarmup['exitCode']) {
            return new JsonResponse([
                'success' => false,
                'step' => 'cache:warmup',
                'output' => $results,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $migration = $this->runConsoleCommand($kernel, [
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ]);
        $results['doctrine:migrations:migrate'] = $migration['output'];

        return new JsonResponse([
            'success' => 0 === $migration['exitCode'],
            'output' => $results,
        ], 0 === $migration['exitCode'] ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param array<string, bool|string> $inputArguments
     *
     * @return array{exitCode:int,output:string}
     */
    private function runConsoleCommand(KernelInterface $kernel, array $inputArguments): array
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $input = new ArrayInput($inputArguments);

        $output = new BufferedOutput();

        try {
            $exitCode = $application->run($input, $output);
        } catch (\Exception $e) {
            return [
                'exitCode' => 1,
                'output' => $e->getMessage()."\n".$output->fetch(),
            ];
        }

        return [
            'exitCode' => $exitCode,
            'output' => $output->fetch(),
        ];
    }

    private function copyDirectoryContents(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination.'/'.substr($item->getPathname(), strlen($source) + 1);

            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0775, true) && !is_dir($targetPath)) {
                    throw new \RuntimeException(sprintf('Unable to create directory "%s".', $targetPath));
                }

                continue;
            }

            $targetDirectory = dirname($targetPath);
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                throw new \RuntimeException(sprintf('Unable to create directory "%s".', $targetDirectory));
            }

            if (!copy($item->getPathname(), $targetPath)) {
                throw new \RuntimeException(sprintf('Unable to copy file "%s".', $targetPath));
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
