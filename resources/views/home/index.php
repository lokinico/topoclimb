<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <h1>TopoclimbCH</h1>
            </div>
            <ul>
                <li><a href="/">Accueil</a></li>
                <li><a href="/regions">Régions</a></li>
                <li><a href="/sites">Sites</a></li>
                <li><a href="/sectors">Secteurs</a></li>
                <li><a href="/login">Connexion</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <h2><?= $title ?></h2>
                <p><?= $description ?></p>
                <a href="/regions" class="btn">Découvrir les sites d'escalade</a>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>Fonctionnalités</h2>
                <div class="feature-grid">
                    <div class="feature">
                        <h3>Sites d'escalade</h3>
                        <p>Découvrez tous les sites d'escalade en Suisse avec leurs informations détaillées.</p>
                    </div>
                    <div class="feature">
                        <h3>Voies d'escalade</h3>
                        <p>Parcourez les voies d'escalade avec leurs difficultés, styles et équipements.</p>
                    </div>
                    <div class="feature">
                        <h3>Conditions</h3>
                        <p>Consultez les conditions actuelles des sites et partagez vos observations.</p>
                    </div>
                    <div class="feature">
                        <h3>Événements</h3>
                        <p>Participez à des sorties d'escalade et autres événements de la communauté.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> TopoclimbCH - Tous droits réservés</p>
        </div>
    </footer>

    <script src="/js/app.js"></script>
</body>
</html>