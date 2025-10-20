<?php
session_start();

// ==========================
// CONFIGURATION & AUTOLOAD
// ==========================
$dsn = 'mysql:host=localhost;dbname=auto_exam;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die('Erreur BDD : ' . $e->getMessage());
}

// ==========================
// ENTITÉS (simplifiées)
// ==========================
class Car {
    public $id, $marque, $modele, $annee, $image, $prix;
    public function __construct($data) {
        foreach ($data as $k => $v) $this->$k = $v;
    }
}
class Article {
    public $id, $titre, $contenu, $date_publication, $voiture_id;
    public function __construct($data) {
        foreach ($data as $k => $v) $this->$k = $v;
    }
}
class Offer {
    public $id, $titre, $description, $prix_promo, $date_validite, $voiture_id;
    public function __construct($data) {
        foreach ($data as $k => $v) $this->$k = $v;
    }
}
class Contact {
    public $id, $nom, $prenom, $email, $telephone, $commentaire, $voiture_id, $date_creation;
    public function __construct($data) {
        foreach ($data as $k => $v) $this->$k = $v;
    }
}

// ==========================
// REPOSITORIES (simplifiés)
// ==========================
function getAll($table, $order = 'id DESC') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table ORDER BY $order");
    $stmt->execute();
    return $stmt->fetchAll();
}
function getById($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    return $data ? new ($table === 'cars' ? 'Car' : ($table === 'articles' ? 'Article' : ($table === 'offers' ? 'Offer' : 'Contact')))($data) : null;
}
function delete($table, $id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    return $stmt->execute([$id]);
}
function save($table, $data, $id = null) {
    global $pdo;
    if ($id) {
        $fields = implode(' = ?, ', array_keys($data)) . ' = ?';
        $stmt = $pdo->prepare("UPDATE $table SET $fields WHERE id = ?");
        $values = array_values($data);
        $values[] = $id;
    } else {
        $cols = implode(', ', array_keys($data));
        $placeholders = str_repeat('?,', count($data) - 1) . '?';
        $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
        $values = array_values($data);
    }
    return $stmt->execute($values);
}

// ==========================
// ADMIN AUTH
// ==========================
$adminUser = 'admin';
$adminPassHash = password_hash('admin123', PASSWORD_DEFAULT); // change en prod

if ($_POST['login'] ?? false) {
    if ($_POST['username'] === $adminUser && password_verify($_POST['password'], $adminPassHash)) {
        $_SESSION['admin'] = true;
    } else {
        $error = "Identifiants invalides.";
    }
}
$isAdmin = $_SESSION['admin'] ?? false;

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin']);
    header('Location: ?');
    exit;
}

// ==========================
// ROUTAGE SIMPLE
// ==========================
$page = $_GET['page'] ?? 'home';
$car_id = $_GET['car_id'] ?? null;
$article_id = $_GET['article_id'] ?? null;
$offer_id = $_GET['offer_id'] ?? null;

// ==========================
// TRAITEMENTS ADMIN
// ==========================
if ($isAdmin) {
    // Suppression
    if ($_GET['delete'] ?? false) {
        delete($_GET['type'], $_GET['id']);
        header('Location: ?page=' . $_GET['type'] . 's');
        exit;
    }

    // Sauvegarde voiture
    if ($_POST['save_car'] ?? false) {
        $data = [
            'marque' => $_POST['marque'],
            'modele' => $_POST['modele'],
            'annee' => (int)$_POST['annee'],
            'prix' => (float)$_POST['prix'],
            'image' => $_POST['image'] ?: 'default.jpg'
        ];
        save('cars', $data, $_POST['id'] ?? null);
        header('Location: ?page=cars');
        exit;
    }

    // Sauvegarde article
    if ($_POST['save_article'] ?? false) {
        $data = [
            'titre' => $_POST['titre'],
            'contenu' => $_POST['contenu'],
            'date_publication' => date('Y-m-d H:i:s'),
            'voiture_id' => $_POST['voiture_id'] ?: null
        ];
        save('articles', $data, $_POST['id'] ?? null);
        header('Location: ?page=articles');
        exit;
    }

    // Sauvegarde offre
    if ($_POST['save_offer'] ?? false) {
        $data = [
            'titre' => $_POST['titre'],
            'description' => $_POST['description'],
            'prix_promo' => (float)$_POST['prix_promo'],
            'date_validite' => $_POST['date_validite'],
            'voiture_id' => $_POST['voiture_id']
        ];
        save('offers', $data, $_POST['id'] ?? null);
        header('Location: ?page=offers');
        exit;
    }

    // Sauvegarde contact (admin ne le fait pas, mais suppression OK)
    if ($_POST['save_contact'] ?? false) {
        // Non autorisé — les contacts viennent du formulaire public
    }
}

// Formulaire de contact (public)
if ($_POST['submit_contact'] ?? false) {
    $errors = [];
    $required = ['nom', 'prenom', 'email', 'telephone', 'commentaire', 'voiture_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) $errors[] = "$field requis";
    }
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "email invalide";
    if (!ctype_digit(str_replace([' ', '.', '-'], '', $_POST['telephone']))) $errors[] = "téléphone invalide";

    if (empty($errors)) {
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'telephone' => $_POST['telephone'],
            'commentaire' => $_POST['commentaire'],
            'voiture_id' => (int)$_POST['voiture_id'],
            'date_creation' => date('Y-m-d H:i:s')
        ];
        save('contacts', $data);
        $contact_success = "Merci ! Votre demande a été enregistrée.";
    }
}

// ==========================
// VUES
// ==========================
function e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>AutoSite</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .admin-bar { background: #eee; padding: 0.5rem; margin-bottom: 1rem; }
        .card { border: 1px solid #ccc; padding: 1rem; margin: 1rem 0; }
        form label { display: block; margin-top: 0.5rem; }
        input, textarea, button { padding: 0.4rem; margin: 0.2rem 0; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>

<?php if ($isAdmin): ?>
    <div class="admin-bar">
        👤 Admin — <a href="?page=cars">Voitures</a> | 
        <a href="?page=articles">Articles</a> | 
        <a href="?page=offers">Offres</a> | 
        <a href="?page=contacts">Contacts</a> | 
        <a href="?action=logout">Déconnexion</a>
    </div>
<?php else: ?>
    <div>
        <a href="?">Accueil</a> | 
        <a href="?page=articles">Actualités</a> | 
        <a href="?page=offers">Offres</a> |
        <?php if (!$isAdmin): ?><a href="?page=login">Connexion Admin</a><?php endif; ?>
    </div>
<?php endif; ?>

<h1>Mini Site Automobile</h1>

<?php if (isset($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if (isset($contact_success)): ?><p class="success"><?= e($contact_success) ?></p><?php endif; ?>

<?php
// ================== PAGES ==================
if ($page === 'login' && !$isAdmin): ?>
    <h2>Connexion Admin</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="admin" required><br>
        <input type="password" name="password" placeholder="mot de passe" required><br>
        <button type="submit" name="login" value="1">Se connecter</button>
    </form>

<?php elseif ($page === 'home'): ?>
    <h2>Voitures</h2>
    <?php foreach (getAll('cars') as $row):
        $car = new Car($row); ?>
        <div class="card">
            <h3><?= e($car->marque . ' ' . $car->modele) ?> (<?= $car->annee ?>)</h3>
            <p>Prix : <?= number_format($car->prix, 2, ',', ' ') ?> €</p>
            <img src="<?= e($car->image) ?>" alt="" height="100">
            <p><a href="?page=car_detail&car_id=<?= $car->id ?>">Voir détail</a></p>
        </div>
    <?php endforeach; ?>

<?php elseif ($page === 'car_detail' && $car_id): ?>
    <?php $car = getById('cars', $car_id); ?>
    <h2><?= e($car->marque . ' ' . $car->modele) ?></h2>
    <p>Année : <?= $car->annee ?></p>
    <p>Prix : <?= number_format($car->prix, 2, ',', ' ') ?> €</p>
    <img src="<?= e($car->image) ?>" alt="" height="200">
    <h3>Contacter pour ce modèle</h3>
    <form method="POST">
        <input type="hidden" name="voiture_id" value="<?= $car->id ?>">
        <label>Nom: <input name="nom" required></label>
        <label>Prénom: <input name="prenom" required></label>
        <label>Email: <input type="email" name="email" required></label>
        <label>Téléphone: <input name="telephone" required></label>
        <label>Commentaire: <textarea name="commentaire" required></textarea></label>
        <button type="submit" name="submit_contact" value="1">Envoyer</button>
    </form>

<?php elseif ($page === 'articles'): ?>
    <h2>Actualités</h2>
    <?php foreach (getAll('articles', 'date_publication DESC') as $row):
        $art = new Article($row);
        $car = $art->voiture_id ? getById('cars', $art->voiture_id) : null; ?>
        <div class="card">
            <h3><?= e($art->titre) ?></h3>
            <p><?= e(substr($art->contenu, 0, 100)) ?>...</p>
            <small> Publié le <?= $art->date_publication ?></small>
            <?php if ($car): ?>
                <p>Lié à : <a href="?page=car_detail&car_id=<?= $car->id ?>"><?= e($car->marque . ' ' . $car->modele) ?></a></p>
            <?php endif; ?>
            <a href="?page=article_detail&article_id=<?= $art->id ?>">Lire</a>
            <?php if ($isAdmin): ?>
                | <a href="?page=edit_article&id=<?= $art->id ?>">✏️</a>
                | <a href="?type=articles&id=<?= $art->id ?>&delete=1" onclick="return confirm('Supprimer ?')">🗑️</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php elseif ($page === 'article_detail' && $article_id): ?>
    <?php $art = getById('articles', $article_id);
          $car = $art->voiture_id ? getById('cars', $art->voiture_id) : null; ?>
    <h2><?= e($art->titre) ?></h2>
    <p><?= e($art->contenu) ?></p>
    <p> Publié le <?= $art->date_publication ?></p>
    <?php if ($car): ?>
        <p>Voiture : <a href="?page=car_detail&car_id=<?= $car->id ?>"><?= e($car->marque . ' ' . $car->modele) ?></a></p>
    <?php endif; ?>

<?php elseif ($page === 'offers'): ?>
    <h2>Offres</h2>
    <?php foreach (getAll('offers') as $row):
        $off = new Offer($row);
        $car = getById('cars', $off->voiture_id); ?>
        <div class="card">
            <h3><?= e($off->titre) ?></h3>
            <p><?= e($off->description) ?></p>
            <p>Promo : <?= number_format($off->prix_promo, 2, ',', ' ') ?> €</p>
            <p>Valide jusqu’au : <?= $off->date_validite ?></p>
            <?php if ($car): ?>
                <p>Voiture : <a href="?page=car_detail&car_id=<?= $car->id ?>"><?= e($car->marque . ' ' . $car->modele) ?></a></p>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                | <a href="?page=edit_offer&id=<?= $off->id ?>">✏️</a>
                | <a href="?type=offers&id=<?= $off->id ?>&delete=1" onclick="return confirm('Supprimer ?')">🗑️</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php
// =============== ADMIN FORMS ===============
if ($isAdmin):
    if ($page === 'cars'): ?>
        <h2>Gestion des voitures</h2>
        <a href="?page=edit_car">➕ Ajouter</a>
        <?php foreach (getAll('cars') as $row):
            $car = new Car($row); ?>
            <div class="card">
                <?= e($car->marque . ' ' . $car->modele) ?> (<?= $car->annee ?>)
                <a href="?page=edit_car&id=<?= $car->id ?>">✏️</a>
                <a href="?type=cars&id=<?= $car->id ?>&delete=1" onclick="return confirm('Supprimer ?')">🗑️</a>
            </div>
        <?php endforeach; ?>

    <?php elseif ($page === 'edit_car'): ?>
        <?php $car = isset($_GET['id']) ? getById('cars', $_GET['id']) : null; ?>
        <h2><?= $car ? 'Modifier' : 'Ajouter' ?> une voiture</h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $car->id ?? '' ?>">
            <label>Marque: <input name="marque" value="<?= e($car->marque ?? '') ?>" required></label>
            <label>Modèle: <input name="modele" value="<?= e($car->modele ?? '') ?>" required></label>
            <label>Année: <input type="number" name="annee" value="<?= $car->annee ?? '' ?>" required></label>
            <label>Prix: <input type="number" step="0.01" name="prix" value="<?= $car->prix ?? '' ?>" required></label>
            <label>Image URL: <input name="image" value="<?= e($car->image ?? '') ?>"></label>
            <button type="submit" name="save_car" value="1">Enregistrer</button>
        </form>

    <?php elseif ($page === 'edit_article'): ?>
        <?php $art = isset($_GET['id']) ? getById('articles', $_GET['id']) : null; ?>
        <h2><?= $art ? 'Modifier' : 'Ajouter' ?> un article</h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $art->id ?? '' ?>">
            <label>Titre: <input name="titre" value="<?= e($art->titre ?? '') ?>" required></label>
            <label>Contenu: <textarea name="contenu" required><?= e($art->contenu ?? '') ?></textarea></label>
            <label>Voiture (optionnel): 
                <select name="voiture_id">
                    <option value="">Aucune</option>
                    <?php foreach (getAll('cars') as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($art && $art->voiture_id == $c['id']) ? 'selected' : '' ?>>
                            <?= e($c['marque'] . ' ' . $c['modele']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" name="save_article" value="1">Enregistrer</button>
        </form>

    <?php elseif ($page === 'edit_offer'): ?>
        <?php $off = isset($_GET['id']) ? getById('offers', $_GET['id']) : null; ?>
        <h2><?= $off ? 'Modifier' : 'Ajouter' ?> une offre</h2>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $off->id ?? '' ?>">
            <label>Titre: <input name="titre" value="<?= e($off->titre ?? '') ?>" required></label>
            <label>Description: <textarea name="description" required><?= e($off->description ?? '') ?></textarea></label>
            <label>Prix promo: <input type="number" step="0.01" name="prix_promo" value="<?= $off->prix_promo ?? '' ?>" required></label>
            <label>Date validité: <input type="date" name="date_validite" value="<?= $off->date_validite ?? '' ?>" required></label>
            <label>Voiture:
                <select name="voiture_id" required>
                    <?php foreach (getAll('cars') as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($off && $off->voiture_id == $c['id']) ? 'selected' : '' ?>>
                            <?= e($c['marque'] . ' ' . $c['modele']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" name="save_offer" value="1">Enregistrer</button>
        </form>

    <?php elseif ($page === 'contacts'): ?>
        <h2>Messages de contact</h2>
        <?php foreach (getAll('contacts', 'date_creation DESC') as $row):
            $ct = new Contact($row);
            $car = getById('cars', $ct->voiture_id); ?>
            <div class="card">
                <strong><?= e($ct->nom . ' ' . $ct->prenom) ?></strong> — <?= $ct->date_creation ?><br>
                Email: <?= e($ct->email) ?> | Tel: <?= e($ct->telephone) ?><br>
                Commentaire: <?= e($ct->commentaire) ?><br>
                Voiture: <?= $car ? e($car->marque . ' ' . $car->modele) : 'Inconnue' ?>
                <a href="?type=contacts&id=<?= $ct->id ?>&delete=1" onclick="return confirm('Supprimer ?')">🗑️</a>
            </div>
        <?php endforeach; ?>
    <?php endif;
endif;
?>

</body>
</html>