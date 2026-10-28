<?php 
declare(strict_types=1); 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');
    $pass_repeat = trim($_POST['pass_repeat'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $user)) {
        $error = 'Login może zawierać tylko litery, cyfry oraz znaki _ i -';
    } elseif (!empty($user) && !empty($pass) && !empty($pass_repeat)) {
        if ($pass !== $pass_repeat) {
            $error = 'Hasła nie są identyczne!';
        } else {
            // Połączenie z bazą przez plik db.php
            require_once 'db.php';

            $user_safe = mysqli_real_escape_string($link, $user);

            $check_sql = "SELECT id FROM users WHERE username = '$user_safe' LIMIT 1";
            $check_res = mysqli_query($link, $check_sql);

            if ($check_res && mysqli_num_rows($check_res) > 0) {
                $error = 'Użytkownik o takiej nazwie już istnieje!';
            } else {
                $avatar_name = 'default.png';
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    if (in_array($ext, $allowed, true)) {
                        if (!is_dir('avatars')) {
                            @mkdir('avatars', 0777, true);
                        }
                        $avatar_name = time() . '_' . uniqid() . '.' . $ext;
                        move_uploaded_file($_FILES['avatar']['tmp_name'], 'avatars/' . $avatar_name);
                    }
                }

                $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
                $insert_sql = "INSERT INTO users (username, password, avatar) VALUES ('$user_safe', '$pass_hash', '$avatar_name')";

                if (mysqli_query($link, $insert_sql)) {
                    // Tworzenie katalogu na piosenki użytkownika w MySpotify
                    $user_dir = 'songs/' . $user;
                    if (!is_dir('songs')) {
                        @mkdir('songs', 0777, true);
                    }
                    if (!is_dir($user_dir)) {
                        @mkdir($user_dir, 0777, true);
                    }

                    $success = 'Konto zostało pomyślnie utworzone! Możesz się zalogować.';
                } else {
                    $error = 'Błąd podczas rejestracji w bazie danych.';
                }
            }
            mysqli_close($link);
        }
    } else {
        $error = 'Wypełnij wszystkie wymagane pola.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medaj</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="twoj_css.css">
</head>
<body class="d-flex flex-column min-vh-100">

    <?php require_once 'header.php'; ?>

    <main class="container flex-grow-1" style="margin-top: 100px; margin-bottom: 50px;">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <form method="post" action="rejestruj.php" enctype="multipart/form-data" class="p-4 border border-danger rounded bg-dark text-light shadow-lg">
                    <h1 class="h3 mb-3 fw-bold text-center text-danger">Rejestracja konta</h1>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger text-center p-2"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success text-center p-2">
                            <?php echo htmlspecialchars($success); ?>
                            <div class="mt-2"><a href="logowanie.php" class="btn btn-sm btn-outline-success">Przejdź do logowania</a></div>
                        </div>
                    <?php endif; ?>

                    <div class="form-floating mb-3 text-dark">
                        <input type="text" class="form-control" id="floatingUser" name="user" placeholder="Login" required autofocus>
                        <label for="floatingUser">Nazwa użytkownika (Login)</label>
                    </div>

                    <div class="form-floating mb-3 text-dark">
                        <input type="password" class="form-control" id="floatingPassword" name="pass" placeholder="Hasło" required>
                        <label for="floatingPassword">Hasło</label>
                    </div>

                    <div class="form-floating mb-3 text-dark">
                        <input type="password" class="form-control" id="floatingRepeat" name="pass_repeat" placeholder="Powtórz hasło" required>
                        <label for="floatingRepeat">Powtórz hasło</label>
                    </div>

                    <div class="mb-3">
                        <label for="avatar" class="form-label text-light" style="font-size: 0.9rem;">Wybierz awatar (opcjonalnie):</label>
                        <input class="form-control bg-dark text-light border-secondary" type="file" id="avatar" name="avatar" accept="image/*">
                    </div>

                    <button class="w-100 btn btn-lg btn-danger fw-bold mt-2" type="submit">Zarejestruj się</button>

                    <div class="text-center mt-3">
                        <a href="logowanie.php" class="text-warning text-decoration-none">Masz już konto? Zaloguj się</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php require_once 'footer.php'; ?>
</body>
</html>