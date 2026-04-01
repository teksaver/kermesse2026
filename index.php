<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Hébergement PHP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --bg-color: #0F172A;
            --card-bg: #1E293B;
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 600px;
            width: 100%;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3B82F6, #8B5CF6, #EC4899);
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(to right, #60A5FA, #A78BFA);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: var(--text-muted);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .php-info {
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .php-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .php-info h2 {
            font-size: 1.2rem;
            margin-top: 0;
            color: #E2E8F0;
            font-weight: 500;
        }

        .version {
            font-family: monospace;
            font-size: 1.5rem;
            color: #10B981;
            margin: 10px 0;
            font-weight: 700;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: rgba(16, 185, 129, 0.1);
            color: #34D399;
            border-radius: 9999px;
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #10B981;
            border-radius: 50%;
            box-shadow: 0 0 10px #10B981;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Félicitations !</h1>
        <p>Si vous voyez cette page, cela signifie que votre déploiement SFTP a fonctionné et que l'hébergement exécute correctement le PHP.</p>
        
        <div class="status">
            <div class="status-dot"></div>
            Système opérationnel
        </div>

        <div class="php-info">
            <h2>Informations Serveur</h2>
            <div class="version">
                PHP <?php echo phpversion(); ?>
            </div>
            <p style="margin-bottom: 0; font-size: 0.9rem;">
                Serveur synchronisé à : <?php echo date('H:i:s, d/m/Y'); ?>
            </p>
        </div>
    </div>
</body>
</html>
