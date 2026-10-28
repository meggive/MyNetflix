<?php 
declare(strict_types=1); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
<div class="container">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top border-bottom border-danger mt-0 mb-0 ms-0 me-0 pt-1 pb-1 ps-0 pe-0">
  <div class="container-fluid">
    
    <!-- NOWE LOGO I POWRÓT DO CENTRUM DOWODZENIA -->
    <a class="navbar-brand d-flex align-items-center" href="/index.php">
        <div class="logo-sm">SM</div>
        <span class="ms-3 fw-light title-text">CENTRUM DOWODZENIA</span>
    </a>
<ul class="navbar-nav ms-auto">
        <!-- Zmieniony warunek sprawdzający user_id -->
        <?php if (isset($_SESSION['user_id'])): ?>
            
            <!-- Widok dla ZALOGOWANEGO użytkownika -->
            <li class="nav-item d-flex align-items-center">
                <!-- Wyświetlenie awatara z zabezpieczeniem -->
                <img src="avatars/<?php echo isset($_SESSION['avatar']) ? $_SESSION['avatar'] : 'default.png'; ?>" alt="Avatar" width="32" height="32" class="rounded-circle me-2" style="object-fit: cover;">
                <!-- Powitanie po imieniu z poprawnej zmiennej -->
                <span class="nav-link text-white me-3">Witaj, <b><?php echo htmlspecialchars($_SESSION['username']); ?></b>!</span>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger fw-bold" href="logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-right" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                        <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                    </svg> 
                    Wyloguj się
                </a>
            </li>

        <?php else: ?>
            
            <!-- Widok dla NIEZALOGOWANEGO użytkownika -->
            <li class="nav-item">
                <a class="nav-link text-white fw-bold" href="logowanie.php">Zaloguj się</a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-warning fw-bold" href="rejestruj.php">Rejestracja</a>
            </li>
            
        <?php endif; ?>
      </ul>
  </div>
</nav>
</div>
</header>