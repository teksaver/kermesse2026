<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(Connection $connection): Response
    {
        try {
            $dbStatus = 'Connecté';
            $dbMessage = 'Connexion à la base de données réussie via Doctrine.';
            $isConnected = true;

            $schemaManager = $connection->createSchemaManager();
            $tables = $schemaManager->listTableNames();
        } catch (\Exception $e) {
            $dbStatus = 'Erreur';
            $dbMessage = $e->getMessage();
            $isConnected = false;
            $tables = [];
        }

        return $this->render('default/index.html.twig', [
            'dbStatus' => $dbStatus,
            'dbMessage' => $dbMessage,
            'isConnected' => $isConnected,
            'tables' => $tables,
            'user' => $this->getUser(),
        ]);
    }
}
