<?php 
declare(strict_types=1);

// WŁĄCZENIE WYŚWIETLANIA BŁĘDÓW (DIAGNOSTYKA)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Przekierowanie zalogowanego użytkownika
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    if (!empty($user) && !empty($pass)) {
        require_once 'db.php';

        // Sprawdzenie, czy połączenie z bazą działa
        if (!$link) {
            $error = 'Brak połączenia z bazą danych! Sprawdź plik db.php.';
        } else {
            $user_safe = mysqli_real_escape_string($link, $user);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            // 1. Sprawdzenie blokady Brute-Force w break_ins
            $res_block = @mysqli_query($link, "SELECT id FROM break_ins WHERE username = '$user_safe' AND `datetime` > NOW() - INTERVAL 1 MINUTE ORDER BY id DESC LIMIT 1");

            if ($res_block && mysqli_num_rows($res_block) > 0) {
                $error = 'Konto jest zablokowane na 1 minutę z powodu 3 nieudanych prób logowania!';
                mysqli_close($link);
            } else {
                // 2. Pobranie konta użytkownika z bazy
                $sql = "SELECT * FROM users WHERE username = '$user_safe' LIMIT 1";
                $result = mysqli_query($link, $sql);

                if ($result && mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);

                    // Weryfikacja hasła (Bcrypt oraz MD5 / tekst pisany)
                    if (password_verify($pass, $row['password']) || $pass === $row['password'] || md5($pass) === $row['password']) {
                        
                        // Ostrzeżenie o nieudanej próbie logowania
                        $res_fail = @mysqli_query($link, "SELECT `datetime`, ipaddress FROM goscieportalu WHERE username = '$user_safe' AND status = 'failed' ORDER BY id DESC LIMIT 1");
                        if ($res_fail && mysqli_num_rows($res_fail) > 0) {
                            $fail_row = mysqli_fetch_assoc($res_fail);
                            $last_time = $fail_row['datetime'] ?? '';
                            $last_ip = $fail_row['ipaddress'] ?? '';
                            $_SESSION['warning_msg'] = "Uwaga! Ostatnia nieudana próba logowania: " . $last_time . " z IP: " . $last_ip;
                        }

                        // Rejestracja pomyślnego logowania
                        @mysqli_query($link, "INSERT INTO goscieportalu (ipaddress, username, status, `datetime`) VALUES ('$ip', '$user_safe', 'success', NOW())");

                        // Ustawienie zmiennych sesyjnych
                        $_SESSION['user_id'] = (int)$row['id'];
                        $_SESSION['idu'] = (int)$row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['avatar'] = !empty($row['avatar']) ? $row['avatar'] : 'default.png';

                        // Tworzenie katalogu na filmy w MyNetflix
                        $films_dir = 'films/' . $row['username'];
                        if (!is_dir('films')) {
                            @mkdir('films', 0777, true);
                        }
                        if (!is_dir($films_dir)) {
                            @mkdir($films_dir, 0777, true);
                        }

                        $_SESSION['user_home'] = $films_dir;
                        $_SESSION['current_dir'] = $films_dir;

                        session_write_close();
                        mysqli_close($link);

                        header('Location: index.php');
                        exit;
                    }
                }

                // 3. Logowanie nieudane - zapis próby
                @mysqli_query($link, "INSERT INTO goscieportalu (ipaddress, username, status, `datetime`) VALUES ('$ip', '$user_safe', 'failed', NOW())");

                // Sprawdzenie liczby ostatnich błędnych prób
                $res_recent = @mysqli_query($link, "SELECT status FROM goscieportalu WHERE username = '$user_safe' ORDER BY id DESC LIMIT 3");
                $fail_count = 0;
                if ($res_recent) {
                    while ($f = mysqli_fetch_assoc($res_recent)) {
                        if (($f['status'] ?? '') === 'failed') {
                            $fail_count++;
                        }
                    }
                }

                if ($fail_count >= 3) {
                    @mysqli_query($link, "INSERT INTO break_ins (ip, username, `datetime`) VALUES ('$ip', '$user_safe', NOW())");
                    $error = 'Trzykrotne błędne logowanie! Konto zostało zablokowane na 1 minutę.';
                } else {
                    $error = 'Nieprawidłowy login lub hasło!';
                }

                mysqli_close($link);
            }
        }
    } else {
        $error = 'Proszę podać login i hasło.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyNetflix - Logowanie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="twoj_css.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-black text-light">

    <?php require_once 'header.php'; ?>

    <main class="container flex-grow-1" style="margin-top: 100px; margin-bottom: 50px;">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <form method="post" action="logowanie.php" class="p-4 border border-danger rounded bg-dark text-light shadow-lg">
                    <h1 class="h3 mb-3 fw-bold text-center text-danger">Zaloguj się</h1>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger text-center p-2" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-floating mb-3 text-dark">
                        <input type="text" class="form-control" id="floatingInput" name="user" placeholder="Login" required autofocus>
                        <label for="floatingInput">Login</label>
                    </div>

                    <div class="form-floating mb-3 text-dark">
                        <input type="password" class="form-control" id="floatingPassword" name="pass" placeholder="Hasło" required>
                        <label for="floatingPassword">Hasło</label>
                    </div>

                    <button class="w-100 btn btn-lg btn-danger fw-bold" type="submit">Zaloguj</button>

                    <div class="text-center mt-3">
                        <a href="rejestruj.php" class="text-warning text-decoration-none">Nie masz konta? Zarejestruj się</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php require_once 'footer.php'; ?>
</body>
</html>