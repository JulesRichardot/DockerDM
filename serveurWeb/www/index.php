<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8" />
        <title>Exo 2 - Docker Compose</title>
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
    <div class="wrapper">
        <h1>Messagerie</h1>

        <?php
        $host = 'db';     
        $port = 3306;
        $db   = 'mydb';
        $user = 'user';
        $pass = 'passwd';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            echo "Connexion PDO OK à $db via $host:$port<br>";

            // Créer la table si elle n'existe pas
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Traitement du formulaire POST
            if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['message'])) {
                $message = $_POST['message'];
                $stmt = $pdo->prepare("INSERT INTO messages (content) VALUES (?)");
                $stmt->execute([$message]);
                echo "<p style='color: green;'>Message ajouté avec succès!</p>";
            }

            // Afficher les messages
            $stmt = $pdo->query("SELECT content, created_at FROM messages ORDER BY created_at DESC");
            $messages = $stmt->fetchAll();
            
            if (count($messages) > 0) {
                echo "<div class='messages'><h2>Messages récents:</h2>";
                foreach($messages as $row) {
                    echo "<div class='message'><p>" . htmlspecialchars($row['content']) . "</p><span class='timestamp'>" . $row['created_at'] . "</span></div>";
                }
                echo "</div>";
            } else {
                echo "<p>Aucun message pour le moment.</p>";
            }

        } catch (Throwable $e) {
            echo "Erreur de connexion : " . htmlspecialchars($e->getMessage());
        }
        ?>

        <div class="panel">
        <h2>Ajouter un message</h2>
        <form method="POST" action="">
            <textarea name="message" rows="4" cols="50" placeholder="Tapez votre message ici..." required></textarea><br><br>
            <button type="submit">Envoyer</button>
        </form>
        <p>Bienvenue sur la messagerie. Ajoutez un message et retrouvez-le ci-dessus.</p>
        </div>

        <hr />
    </div>

    <!-- INDISPENSABLE -->
    <div id="reference">
        <p>Ce site Web est fièrement à <a href="https://brutalist-web.design/">design brutaliste</a> !</p>
    </div>
    </body>
</html>