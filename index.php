
<?php
session_start();

$message = '';
$type = 'info';

if (!isset($_SESSION['db'])) {
    $_SESSION['db'] = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'connect') {
            $db = $_POST['db_name'];
            $user = $_POST['db_user'];
            $pass = $_POST['db_password'];

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
                throw new Exception('Invalid database name.');
            }

            $pdo = new PDO(
                "mysql:host=localhost;dbname=$db;charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL
                )
            ");

            $_SESSION['db'] = [
                'name' => $db,
                'user' => $user,
                'password' => $pass
            ];

            $message = 'Database connected and users table created!';
            $type = 'success';
        }

        elseif (in_array($_POST['action'], ['add', 'login'])) {
            if (!$_SESSION['db']) {
                throw new Exception('Configure the database first.');
            }

            $c = $_SESSION['db'];

            $pdo = new PDO(
                "mysql:host=localhost;dbname={$c['name']};charset=utf8mb4",
                $c['user'],
                $c['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            if ($_POST['action'] === 'add') {
                $username = trim($_POST['username']);
                $password = $_POST['password'];

                if ($username === '' || strlen($username) > 100 ||
                    strlen($password) < 8) {
                    throw new Exception(
                        'Enter a valid username and a password of at least 8 characters.'
                    );
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO users (username, password_hash)
                     VALUES (?, ?)"
                );

                $stmt->execute([
                    $username,
                    password_hash($password, PASSWORD_DEFAULT)
                ]);

                $message = "User $username created successfully!";
                $type = 'success';
            }

            else {
                $stmt = $pdo->prepare(
                    "SELECT id, username, password_hash
                     FROM users WHERE username = ?"
                );

                $stmt->execute([trim($_POST['username'])]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($account &&
                    password_verify($_POST['password'], $account['password_hash'])) {
                    $message = 'Authentication successful! Welcome ' .
                               htmlspecialchars($account['username']);
                    $type = 'success';
                } else {
                    $message = 'Login failed: invalid credentials.';
                    $type = 'error';
                }
            }
        }

        elseif ($_POST['action'] === 'disconnect') {
            $_SESSION['db'] = null;
            $message = 'Database configuration cleared.';
            $type = 'success';
        }

    } catch (PDOException $e) {
        $message = $e->getCode() === '23000'
            ? 'Username already exists.'
            : 'Database error. Check your connection and settings.';
        error_log($e->getMessage());
        $type = 'error';

    } catch (Throwable $e) {
        $message = $e->getMessage();
        $type = 'error';
    }
}

$connected = !empty($_SESSION['db']);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>ZBICHA User Database Manager</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
rel="stylesheet">

<style>
:root {
    --primary: #4355db;
    --dark: #17233c;
    --muted: #78849a;
    --background: #f4f6fb;
}

body {
    background: var(--background);
    font-family: Inter, "Segoe UI", sans-serif;
    color: var(--dark);
    min-height: 100vh;
}

.navbar {
    background: white;
    border-bottom: 1px solid #e8ebf2;
    padding: 17px 0;
}

.brand {
    font-size: 19px;
    font-weight: 750;
}

.brand-icon {
    background: var(--primary);
    color: white;
    border-radius: 10px;
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.main-container {
    max-width: 1100px;
    margin: 45px auto;
    padding: 0 20px;
}

.page-heading {
    margin-bottom: 30px;
}

.page-heading h1 {
    font-weight: 750;
    font-size: clamp(25px, 4vw, 34px);
    letter-spacing: -1px;
}

.page-heading p {
    color: var(--muted);
}

.step-card {
    background: white;
    border: 1px solid #e9edf4;
    border-radius: 15px;
    padding: 27px;
    height: 100%;
    box-shadow: 0 5px 20px rgba(30,45,80,.035);
}

.step-number {
    width: 38px;
    height: 38px;
    background: #eef0ff;
    color: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 750;
    margin-bottom: 17px;
}

.step-title {
    font-size: 19px;
    font-weight: 700;
}

.step-description {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 23px;
}

.form-label {
    font-size: 13px;
    font-weight: 650;
    color: #46536a;
}

.form-control {
    padding: 11px 13px;
    border-radius: 8px;
    border-color: #dfe4ed;
    font-size: 14px;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67,85,219,.10);
}

.btn-primary {
    background: var(--primary);
    border-color: var(--primary);
    padding: 11px 17px;
    border-radius: 8px;
    font-weight: 650;
    font-size: 14px;
}

.btn-primary:hover {
    background: #3445c4;
    border-color: #3445c4;
}

.status-box {
    padding: 14px 17px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-size: 14px;
}

.status-success {
    background: #eaf8f0;
    color: #187644;
}

.status-error {
    background: #fff0f0;
    color: #bd3434;
}

.security-note {
    background: #f7f8fc;
    border: 1px solid #edf0f5;
    padding: 13px;
    border-radius: 9px;
    color: #718096;
    font-size: 12px;
    line-height: 1.6;
}

.footer {
    text-align: center;
    color: #98a2b3;
    font-size: 12px;
    margin: 40px 0 20px;
}

@media(max-width:767px) {
    .main-container {
        margin-top: 28px;
        padding: 0 14px;
    }
    .step-card {
        padding: 21px;
    }
}
</style>
</head>

<body>

<nav class="navbar">
    <div class="container">
        <div class="brand d-flex align-items-center">
            <span class="brand-icon">
                <i class="bi bi-database-fill"></i>
            </span>
            ZBICHA User Database Manager
        </div>

        <span class="badge <?= $connected ? 'text-bg-success' : 'text-bg-light' ?>">
            <?= $connected ? 'Connected' : 'Setup Required' ?>
        </span>
    </div>
</nav>

<main class="main-container">

    <div class="page-heading">
        <h1>Database & Authentication Setup</h1>
        <p>
            Configure your database, create user accounts,
            and test the authentication process.
        </p>
    </div>

    <?php if ($message): ?>
        <div class="status-box status-<?= h($type) ?>">
            <i class="bi <?= $type === 'success'
                ? 'bi-check-circle-fill'
                : 'bi-exclamation-circle-fill' ?>"></i>
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- STEP 1 -->
        <div class="col-lg-4 col-md-6">
            <div class="step-card">

                <div class="step-number">01</div>
                <h2 class="step-title">Database Configuration</h2>
                <p class="step-description">
                    Enter your MySQL connection details.
                </p>

                <?php if (!$connected): ?>

                <form method="POST">
                    <input type="hidden" name="action" value="connect">

                    <div class="mb-3">
                        <label class="form-label">Database Name</label>
                        <input name="db_name" class="form-control"
                               placeholder="my_database"
                               pattern="[A-Za-z0-9_]+" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Database Username</label>
                        <input name="db_user" class="form-control"
                               placeholder="root" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Database Password</label>
                        <input type="password" name="db_password"
                               class="form-control"
                               placeholder="Database password">
                    </div>

                    <button class="btn btn-primary w-100">
                        <i class="bi bi-plug me-2"></i>
                        Connect & Create Table
                    </button>
                </form>

                <?php else: ?>

                <div class="status-box status-success">
                    <i class="bi bi-check-circle-fill"></i>
                    Database successfully configured.
                </div>

                <p class="small text-muted">
                    Database: <strong><?= h($_SESSION['db']['name']) ?></strong>
                </p>

                <p class="small text-muted">
                    Table: <strong>users</strong>
                </p>

                <form method="POST">
                    <input type="hidden" name="action" value="disconnect">
                    <button class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        Change Configuration
                    </button>
                </form>

                <?php endif; ?>

            </div>
        </div>

        <!-- STEP 2 -->
        <div class="col-lg-4 col-md-6">
            <div class="step-card">

                <div class="step-number">02</div>
                <h2 class="step-title">Create New User</h2>
                <p class="step-description">
                    Register a new account in your users table.
                </p>

                <form method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" class="form-control"
                               placeholder="Choose a username"
                               maxlength="100" required
                               <?= !$connected ? 'disabled' : '' ?>>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password"
                               class="form-control"
                               placeholder="Minimum 8 characters"
                               minlength="8" required
                               <?= !$connected ? 'disabled' : '' ?>>
                    </div>

                    <button class="btn btn-primary w-100"
                            <?= !$connected ? 'disabled' : '' ?>>
                        <i class="bi bi-person-plus-fill me-2"></i>
                        Create User Account
                    </button>
                </form>

                <div class="security-note mt-4">
                    <i class="bi bi-shield-lock-fill me-1"></i>
                    Passwords are securely hashed before storage.
                </div>

            </div>
        </div>

        <!-- STEP 3 -->
        <div class="col-lg-4 col-md-12">
            <div class="step-card">

                <div class="step-number">03</div>
                <h2 class="step-title">Test Login</h2>
                <p class="step-description">
                    Verify credentials of an existing user.
                </p>

                <form method="POST">
                    <input type="hidden" name="action" value="login">

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" class="form-control"
                               placeholder="Existing username" required
                               <?= !$connected ? 'disabled' : '' ?>>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password"
                               class="form-control"
                               placeholder="Enter password" required
                               <?= !$connected ? 'disabled' : '' ?>>
                    </div>

                    <button class="btn btn-primary w-100"
                            <?= !$connected ? 'disabled' : '' ?>>
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Authenticate User
                    </button>
                </form>

                <div class="security-note mt-4">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    The submitted password is verified against
                    the stored password hash.
                </div>

            </div>
        </div>

    </div>

    <div class="footer">
        ZBICHA User Database Manager &copy; <?= date('Y') ?>
        <br>PHP &bull; MySQL &bull; PDO
    </div>

</main>

</body>
</html>