<?php
// Configuration
$host = '127.0.0.1'; // utilise 127.0.0.1 au lieu de localhost pour éviter les problèmes DNS
$user = 'root';
$pass = '';
$db = 'testdb';

// Créer la base et la table automatiquement si elles n'existent pas
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Créer la base de données si elle n'existe pas
$conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
$conn->select_db($db);

// Créer la table si elle n'existe pas
$sql ("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    photo VARCHAR(255) DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL
)");
$conn->query($sql);
    

//modifiaction du formulaire pour mettre une photo d'utilisateur
$userPhoto = $_FILES['photo'] ?? null;
if ($userPhoto && $userPhoto['error'] === UPLOAD_ERR_OK) {
    $photoPath = 'uploads/' . basename($userPhoto['name']);
    move_uploaded_file($userPhoto['tmp_name'], $photoPath);
    addfile_get_contents($photoPath);
}

// Traitement du téléchargement de la photo
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);

}

// Declaration du serveur PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion PDO : " . $e->getMessage());
}

// Affichage de la photo dans la liste des utilisateurs
ALTER TABLE users ADD COLUMN photo VARCHAR(255) DEFAULT NULL;
$stmt = $conn->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user && $user['photo']) {
    echo '<img src="' . htmlspecialchars($user[photo]) . '" alt=Photo" style="width:50px;height:50px;border-radius:50%;">';

}

// Traitement des actions
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if ($action === 'add' && $name && $email && $message) {
        $stmt = $conn->prepare("INSERT INTO users (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);
        $stmt->execute();
        $action = 'list';
    }
    
    if ($action === 'edit' && $id && $name && $email && $message) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, message = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $message, $id);
        $stmt->execute();
        $action = 'list';
    }
}

if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $action = 'list';
}

// Récupérer les données pour l'édition
$user = null;
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestion des utilisateurs</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin: 10px 0; }
        input, textarea { padding: 5px; width: 300px; }
        button { padding: 8px 15px; margin: 5px 5px 0 0; }
        table { border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

<?php if ($action === 'add'): ?>
    <h2>Ajouter un utilisateur</h2>
    <form method="POST">
        <div class="form-group">
            <label>Nom:</label>
            <input type="text" name="name" required>
        </div>
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Message:</label>
            <textarea name="message" required></textarea>
        </div>
        <div class="form-group">
            <label>Photo:</label>
            <input type="file" name="photo" accepted="image/*">
        </div>
        <button type="submit">Ajouter</button>
        <a href="?action=list"><button type="button">Annuler</button></a>
    </form>

<?php elseif ($action === 'edit' && $user): ?>
    <h2>Modifier l'utilisateur</h2>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">
        <div class="form-group">
            <label>Nom:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="form-group">
            <label>Message:</label>
            <textarea name="message" required><?= htmlspecialchars($user['message']) ?></textarea>
        </div>
        <button type="submit">Mettre à jour</button>
        <a href="?action=list"><button type="button">Annuler</button></a>
    </form>

<?php else: ?>
    <h2>Liste des utilisateurs</h2>
    <a href="?action=add"><button>Ajouter un utilisateur</button></a>
    
    <?php
    $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
    if ($result->num_rows > 0):
    ?>
    <table>
        <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Message</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['message']) ?></td>
            <td>
                <a href="?action=edit&id=<?= $row['id'] ?>"><button type="button">Modifier</button></a>
                <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Supprimer ?')">
                    <button type="button" style="background:#d9534f;color:white;">Supprimer</button>
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
        <p>Aucun utilisateur trouvé.</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>

