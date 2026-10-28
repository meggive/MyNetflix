<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: logowanie.php');
    exit;
}

require_once 'db.php';

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg_success = '';
$msg_error = '';

// --- 1. DODAWANIE FILMU (VIDEO MP4) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_film') {
    $title = trim($_POST['title'] ?? '');
    $director = trim($_POST['director'] ?? '');
    $idft = (int)($_POST['idft'] ?? 0);
    $subtitles = trim($_POST['subtitles'] ?? '');

    if (!empty($title) && !empty($director) && $idft > 0 && isset($_FILES['film_file']) && $_FILES['film_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['film_file']['name'], PATHINFO_EXTENSION));
        if ($ext === 'mp4') {
            $user_dir = 'films/' . $username;
            if (!is_dir($user_dir)) {
                @mkdir($user_dir, 0777, true);
            }

            $unique_filename = time() . '_' . uniqid() . '.mp4';
            $target_path = $user_dir . '/' . $unique_filename;

            if (move_uploaded_file($_FILES['film_file']['tmp_name'], $target_path)) {
                $title_safe = mysqli_real_escape_string($link, $title);
                $director_safe = mysqli_real_escape_string($link, $director);
                $subtitles_safe = mysqli_real_escape_string($link, $subtitles);

                $cover_filename = 'default_cover.png';
                if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                    $c_ext = strtolower(pathinfo($_FILES['cover_file']['name'], PATHINFO_EXTENSION));
                    $allowed_img = ['jpg', 'jpeg', 'png', 'webp'];
                    if (in_array($c_ext, $allowed_img, true)) {
                        $cover_filename = 'cover_' . time() . '_' . uniqid() . '.' . $c_ext;
                        move_uploaded_file($_FILES['cover_file']['tmp_name'], $user_dir . '/' . $cover_filename);
                    }
                }

                $sql = "INSERT INTO film (title, director, idu, filename, subtitles, idft, cover) 
                        VALUES ('$title_safe', '$director_safe', $user_id, '$unique_filename', '$subtitles_safe', $idft, '$cover_filename')";
                
                if (mysqli_query($link, $sql)) {
                    $msg_success = 'Film został pomyślnie dodany do MyNetflix!';
                } else {
                    $msg_error = 'Błąd zapisu filmu w bazie danych.';
                }
            } else {
                $msg_error = 'Błąd przesyłania pliku na serwer.';
            }
        } else {
            $msg_error = 'Dozwolone są jedynie pliki wideo w formacie MP4!';
        }
    } else {
        $msg_error = 'Wypełnij wszystkie wymagane pola filmu.';
    }
}

// --- 2. TWORZENIE PLAYLISTY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_playlist') {
    $pl_name = trim($_POST['playlist_name'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if (!empty($pl_name)) {
        $pl_safe = mysqli_real_escape_string($link, $pl_name);
        $sql = "INSERT INTO playlistname (idu, name, public) VALUES ($user_id, '$pl_safe', $is_public)";
        if (mysqli_query($link, $sql)) {
            $msg_success = 'Playlista została utworzona!';
        } else {
            $msg_error = 'Błąd podczas tworzenia playlisty.';
        }
    }
}

// --- 3. DODAWANIE FILMU DO PLAYLISTY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_playlist') {
    $idpl = (int)($_POST['idpl'] ?? 0);
    $idf = (int)($_POST['idf'] ?? 0);

    if ($idpl > 0 && $idf > 0) {
        $check_pl = mysqli_query($link, "SELECT idpl FROM playlistname WHERE idpl = $idpl AND idu = $user_id");
        if ($check_pl && mysqli_num_rows($check_pl) > 0) {
            $sql = "INSERT INTO playlistdatabase (idpl, idf) VALUES ($idpl, $idf)";
            if (mysqli_query($link, $sql)) {
                $msg_success = 'Film dodany do playlisty!';
            } else {
                $msg_error = 'Błąd podczas dodawania filmu do playlisty.';
            }
        } else {
            $msg_error = 'Brak uprawnień do edycji tej playlisty.';
        }
    }
}

// --- 4. USUWANIE FILMU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_film') {
    $idf = (int)($_POST['idf'] ?? 0);

    if ($idf > 0) {
        $check_film = mysqli_query($link, "SELECT filename, cover FROM film WHERE idf = $idf AND idu = $user_id LIMIT 1");
        if ($check_film && mysqli_num_rows($check_film) > 0) {
            $film_row = mysqli_fetch_assoc($check_film);
            $filename = $film_row['filename'];

            mysqli_query($link, "DELETE FROM playlistdatabase WHERE idf = $idf");
            if (mysqli_query($link, "DELETE FROM film WHERE idf = $idf AND idu = $user_id")) {
                $file_path = 'films/' . $username . '/' . $filename;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }

                if (!empty($film_row['cover']) && $film_row['cover'] !== 'default_cover.png') {
                    $cover_path = 'films/' . $username . '/' . $film_row['cover'];
                    if (file_exists($cover_path)) {
                        @unlink($cover_path);
                    }
                }
                
                $msg_success = 'Film został usunięty!';
            } else {
                $msg_error = 'Błąd usuwania filmu z bazy danych.';
            }
        } else {
            $msg_error = 'Brak uprawnień do usunięcia tego filmu.';
        }
    }
}

// --- 5. POBIERANIE GATUNKÓW FILMOWYCH ---
$genres_res = mysqli_query($link, "SELECT * FROM filmtype ORDER BY idft ASC");
$genres = [];
if ($genres_res) {
    while ($g = mysqli_fetch_assoc($genres_res)) {
        $genres[] = $g;
    }
}

// --- 6. POBIERANIE WSZYSTKICH FILMÓW ---
$films_sql = "SELECT f.*, ft.name AS genre_name, u.username AS owner 
              FROM film f 
              JOIN filmtype ft ON f.idft = ft.idft 
              JOIN users u ON f.idu = u.id 
              ORDER BY f.idf DESC";
$films_res = mysqli_query($link, $films_sql);
$all_films = [];
if ($films_res) {
    while ($f = mysqli_fetch_assoc($films_res)) {
        $f['src'] = 'films/' . $f['owner'] . '/' . $f['filename'];

        $cover_file = $f['cover'] ?? 'default_cover.png';
        $f['cover_src'] = (!empty($cover_file) && file_exists('films/' . $f['owner'] . '/' . $cover_file)) 
            ? 'films/' . $f['owner'] . '/' . $cover_file 
            : '';

        $all_films[] = $f;
    }
}

// --- 7. POBIERANIE PLAYLIST ORAZ WIDEO W PLAYLISTACH ---
$my_playlists_res = mysqli_query($link, "SELECT * FROM playlistname WHERE idu = $user_id ORDER BY idpl DESC");
$my_playlists = [];
if ($my_playlists_res) {
    while ($p = mysqli_fetch_assoc($my_playlists_res)) {
        $my_playlists[] = $p;
    }
}

$public_playlists_res = mysqli_query($link, "SELECT p.*, u.username AS owner 
                                              FROM playlistname p 
                                              JOIN users u ON p.idu = u.id 
                                              WHERE p.public = 1 AND p.idu != $user_id 
                                              ORDER BY p.idpl DESC");
$public_playlists = [];
if ($public_playlists_res) {
    while ($pp = mysqli_fetch_assoc($public_playlists_res)) {
        $public_playlists[] = $pp;
    }
}

$playlist_map = [];
$pl_all_query = mysqli_query($link, "SELECT pd.idpl, f.*, u.username AS owner 
                                     FROM playlistdatabase pd 
                                     JOIN film f ON pd.idf = f.idf 
                                     JOIN users u ON f.idu = u.id 
                                     ORDER BY pd.idpldb ASC");
if ($pl_all_query) {
    while ($row = mysqli_fetch_assoc($pl_all_query)) {
        $row['src'] = 'films/' . $row['owner'] . '/' . $row['filename'];
        $playlist_map[$row['idpl']][] = $row;
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
    <style>
        .player-video-box {
            width: 100%;
            max-width: 720px;
            aspect-ratio: 16 / 9;
            margin: 0 auto;
            border-radius: 16px;
            background: #000;
            box-shadow: 0 12px 35px rgba(229, 9, 20, 0.3);
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .player-video-box video {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .time-slider {
            accent-color: #e50914;
            cursor: pointer;
        }
        .btn-player-control {
            background: transparent;
            border: none;
            color: #b3b3b3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: color 0.2s ease, transform 0.1s ease, filter 0.2s ease;
        }
        .btn-player-control:hover:not(:disabled) {
            color: #ffffff;
        }
        .btn-player-control:active:not(:disabled), .btn-play-main:active {
            transform: scale(0.85) !important;
            filter: brightness(1.2);
        }
        .btn-player-control.active {
            color: #e50914 !important;
        }
        .btn-player-control.active .icon-shuffle {
            transform: rotate(180deg);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .icon-shuffle {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .btn-play-main {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background-color: #e50914;
            color: #fff;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.5);
            transition: transform 0.2s ease, background-color 0.2s ease;
            cursor: pointer;
        }
        .btn-play-main:hover {
            transform: scale(1.06);
            background-color: #f6121d;
        }
        .icon-play-state, .icon-pause-state {
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .volume-slider {
            -webkit-appearance: none;
            appearance: none;
            width: 100px;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(to right, #e50914 100%, #535353 100%);
            outline: none;
            cursor: pointer;
            vertical-align: middle;
        }
        .volume-slider::-webkit-slider-runnable-track { background: transparent; height: 4px; }
        .volume-slider::-moz-range-track { background: transparent; height: 4px; }
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ffffff;
            cursor: pointer;
            opacity: 0;
            margin-top: -4px;
            transition: opacity 0.15s ease;
        }
        .volume-slider:hover::-webkit-slider-thumb { opacity: 1; }
        .volume-slider::-moz-range-thumb {
            width: 12px;
            height: 12px;
            border: none;
            border-radius: 50%;
            background: #ffffff;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .volume-slider:hover::-moz-range-thumb { opacity: 1; }

        .nav-tabs .nav-link {
            color: #b3b3b3;
            border: none;
            border-bottom: 2px solid transparent;
        }
        .nav-tabs .nav-link.active {
            color: #e50914;
            background: transparent;
            border-bottom: 2px solid #e50914;
            font-weight: bold;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-black text-light">

    <?php require_once 'header.php'; ?>

    <main class="container flex-grow-1" style="margin-top: 85px; margin-bottom: 50px;">

        <?php if (isset($_SESSION['warning_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show text-center" role="alert">
                <?php echo htmlspecialchars($_SESSION['warning_msg']); unset($_SESSION['warning_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($msg_success)): ?>
            <div class="alert alert-success alert-dismissible fade show text-center fw-bold" role="alert">
                <?php echo htmlspecialchars($msg_success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($msg_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show text-center fw-bold" role="alert">
                <?php echo htmlspecialchars($msg_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- CENTRALNY ODTWARZACZ WIDEO VOD -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 col-md-10">
                <div class="p-4 rounded-4 bg-dark border border-secondary shadow-lg text-center">
                    
                    <!-- KONTENER HTML5 VIDEO -->
                    <div class="player-video-box mb-3" id="playerVideoContainer">
                        <video id="videoEngine" preload="metadata" controls style="display:none;"></video>
                        <div id="videoPlaceholder" class="text-secondary fs-1">🎬 Wybierz wideo</div>
                    </div>

                    <h4 class="fw-bold mb-1 text-truncate" id="playerTitle">Wybierz film do odtworzenia</h4>
                    <p class="text-secondary mb-3" id="playerArtist">MyNetflix Player</p>

                    <!-- TIMEBAR Z TIMERAMI -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span id="currentTimeText" class="small text-secondary" style="min-width: 40px; text-align: right;">0:00</span>
                        <input type="range" id="timeSlider" class="form-range time-slider" value="0" min="0" max="100" disabled>
                        <span id="durationText" class="small text-secondary" style="min-width: 40px; text-align: left;">0:00</span>
                    </div>

                    <!-- KONTROLERY Z IKONAMI SVG -->
                    <div class="d-flex justify-content-center align-items-center gap-3">
                        
                        <!-- SHUFFLE -->
                        <button type="button" class="btn-player-control" id="btnShuffle" title="Odtwarzanie losowe" disabled>
                            <svg class="icon-svg icon-shuffle" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M16 3h5v5"></path>
                              <path d="M4 20L21 3"></path>
                              <path d="M21 16v5h-5"></path>
                              <path d="M15 15l6 6"></path>
                              <path d="M4 4l5 5"></path>
                            </svg>
                        </button>

                        <!-- PREVIOUS -->
                        <button type="button" class="btn-player-control" id="btnPrev" title="Poprzedni / Od początku">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                              <path d="M6 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1zm3.207 7.707l7.5 7.5A1 1 0 0 0 18 19.5V4.5a1 1 0 0 0-1.707-.707l-7.5 7.5a1 1 0 0 0 0 1.414z"/>
                            </svg>
                        </button>

                        <!-- PLAY / PAUSE -->
                        <button type="button" class="btn-play-main" id="btnPlayPause" title="Odtwórz / Pauza">
                            <svg id="svgPlay" class="icon-play-state" viewBox="0 0 24 24" width="26" height="26" fill="#fff">
                              <path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11-6.86a1 1 0 0 0 0-1.72l-11-6.86a1 1 0 0 0-1.5.86z"/>
                            </svg>
                            <svg id="svgPause" class="icon-pause-state d-none" viewBox="0 0 24 24" width="26" height="26" fill="#fff">
                              <path d="M6 5h3a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 0h3a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/>
                            </svg>
                        </button>

                        <!-- NEXT -->
                        <button type="button" class="btn-player-control" id="btnNext" title="Następny">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                              <path d="M18 5a1 1 0 0 1 1 1v12a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1zM6.207 4.293A1 1 0 0 0 4.5 5v14a1 1 0 0 0 1.707.707l7.5-7.5a1 1 0 0 0 0-1.414l-7.5-7.5z"/>
                            </svg>
                        </button>

                        <!-- REPEAT -->
                        <button type="button" class="btn-player-control" id="btnRepeat" title="Tryb pętli">
                            <svg id="svgRepeatAll" class="icon-svg" viewBox="0 0 1200 1200" width="22" height="22" fill="none" stroke="currentColor" stroke-width="100" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 150,700 V 400 A 100,100 0 0 1 250,300 H 1000"/>
                                <path d="M 900,150 L 1050,300 L 900,450"/>
                                <path d="M 1050,500 V 800 A 100,100 0 0 1 950,900 H 200"/>
                                <path d="M 300,750 L 150,900 L 300,1050"/>
                            </svg>
                            <svg id="svgRepeatOne" class="icon-svg d-none" viewBox="0 0 1200 1200" width="22" height="22" fill="none" stroke="currentColor" stroke-width="100" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 150,700 V 400 A 100,100 0 0 1 250,300 H 1000"/>
                                <path d="M 900,150 L 1050,300 L 900,450"/>
                                <path d="M 1050,500 V 800 A 100,100 0 0 1 950,900 H 200"/>
                                <path d="M 300,750 L 150,900 L 300,1050"/>
                                <text x="600" y="680" font-family="system-ui, -apple-system, sans-serif" font-size="430" font-weight="700" fill="currentColor" stroke="none" text-anchor="middle">1</text>
                            </svg>
                        </button>
                    </div>

                    <!-- KONTROLER GŁOŚNOŚCI -->
                    <div class="d-flex justify-content-center align-items-center gap-2 mt-3">
                        <button type="button" class="btn-player-control p-0" id="btnMute" title="Wycisz / Włącz dźwięk">
                            <svg id="svgVolume" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                            </svg>
                            <svg id="svgMute" class="d-none" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                            </svg>
                        </button>
                        <input type="range" id="volumeSlider" class="volume-slider" value="100" min="0" max="100" title="Głośność: 100%">
                    </div>

                    <div id="statusBadge" class="mt-3 small text-danger fw-semibold"></div>
                </div>
            </div>
        </div>

        <!-- ZAKŁADKI ZASOBÓW -->
        <ul class="nav nav-tabs justify-content-center mb-4" id="netflixTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fs-5" id="films-tab" data-bs-toggle="tab" data-bs-target="#films-pane">🎬 Wszystkie Filmy</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fs-5" id="playlists-tab" data-bs-toggle="tab" data-bs-target="#playlists-pane">📺 Playlisty</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fs-5" id="add-tab" data-bs-toggle="tab" data-bs-target="#add-pane">➕ Dodaj Film / Playliste</button>
            </li>
        </ul>

        <div class="tab-content" id="netflixTabsContent">
            <!-- TABELA FILMÓW -->
            <div class="tab-pane fade show active" id="films-pane">
                <div class="p-3 bg-dark border border-secondary rounded-3">
                    <?php if (!empty($all_films)): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr class="text-secondary border-bottom border-secondary">
                                        <th style="width: 50px;">#</th>
                                        <th>Tytuł i Reżyser</th>
                                        <th>Gatunek</th>
                                        <th>Dodaj do playlisty</th>
                                        <th class="text-end">Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_films as $idx => $f): 
                                        $is_my_film = ((int)$f['idu'] === $user_id);
                                    ?>
                                        <tr>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger border-0 fw-bold" onclick="playSingleTrack(<?php echo $idx; ?>)">
                                                    ▶
                                                </button>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-light"><?php echo htmlspecialchars($f['title']); ?></div>
                                                <div class="text-secondary small">Reżyser: <?php echo htmlspecialchars($f['director']); ?> | Dodał: <?php echo htmlspecialchars($f['owner']); ?></div>
                                                <?php if (!empty($f['subtitles'])): ?>
                                                    <details class="mt-1">
                                                        <summary class="text-info style-pointer" style="font-size:0.75rem;">Napisy / Opis</summary>
                                                        <p class="small text-light bg-secondary p-2 rounded mt-1 mb-0" style="white-space: pre-wrap; font-size:0.8rem;"><?php echo htmlspecialchars($f['subtitles']); ?></p>
                                                    </details>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars($f['genre_name']); ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($my_playlists)): ?>
                                                    <form action="index.php" method="post" class="d-flex gap-1">
                                                        <input type="hidden" name="action" value="add_to_playlist">
                                                        <input type="hidden" name="idf" value="<?php echo $f['idf']; ?>">
                                                        <select name="idpl" class="form-select form-select-sm bg-black text-light border-secondary" required style="max-width: 140px;">
                                                            <?php foreach ($my_playlists as $mp): ?>
                                                                <option value="<?php echo $mp['idpl']; ?>"><?php echo htmlspecialchars($mp['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-danger">+</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-secondary small">Brak playlist</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($is_my_film): ?>
                                                    <form action="index.php" method="post" onsubmit="return confirm('Czy na pewno chcesz usunąć ten film?');" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_film">
                                                        <input type="hidden" name="idf" value="<?php echo $f['idf']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">🗑️ Usuń</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-secondary small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-secondary py-4">Brak dodanych filmów w bazie danych.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ZAKŁADKA PLAYLIST -->
            <div class="tab-pane fade" id="playlists-pane">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark border border-secondary rounded-3">
                            <h5 class="text-warning mb-3">Moje Playlisty</h5>
                            <?php if (!empty($my_playlists)): ?>
                                <div class="list-group">
                                    <?php foreach ($my_playlists as $mp): ?>
                                        <div class="list-group-item bg-black text-light border-secondary d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($mp['name']); ?></strong>
                                                <?php echo $mp['public'] ? '<span class="badge bg-danger ms-2">Publiczna</span>' : '<span class="badge bg-secondary ms-2">Prywatna</span>'; ?>
                                            </div>
                                            <button class="btn btn-sm btn-danger" onclick="playPlaylist(<?php echo $mp['idpl']; ?>, '<?php echo addslashes($mp['name']); ?>')">
                                                ▶ Odtwórz
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary small">Nie stworzyłeś jeszcze żadnej playlisty.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-dark border border-secondary rounded-3">
                            <h5 class="text-info mb-3">Publiczne Playlisty Innych Użytkowników</h5>
                            <?php if (!empty($public_playlists)): ?>
                                <div class="list-group">
                                    <?php foreach ($public_playlists as $pp): ?>
                                        <div class="list-group-item bg-black text-light border-secondary d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($pp['name']); ?></strong>
                                                <span class="text-secondary small ms-2">(Autor: <?php echo htmlspecialchars($pp['owner']); ?>)</span>
                                            </div>
                                            <button class="btn btn-sm btn-info text-dark fw-bold" onclick="playPlaylist(<?php echo $pp['idpl']; ?>, '<?php echo addslashes($pp['name'] . ' - ' . $pp['owner']); ?>')">
                                                ▶ Odtwórz
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-secondary small">Brak publicznych playlist od innych użytkowników.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ZAKŁADKA FORMULARZY -->
            <div class="tab-pane fade" id="add-pane">
                <div class="row justify-content-center">
                    <div class="col-md-6 mb-4">
                        <div class="p-4 bg-dark border border-secondary rounded-3">
                            <h5 class="text-danger mb-3">➕ Dodaj film MP4</h5>
                            <form action="index.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_film">
                                <div class="mb-2">
                                    <label class="form-label small">Tytuł filmu *</label>
                                    <input type="text" name="title" class="form-control bg-black text-light border-secondary" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Reżyser *</label>
                                    <input type="text" name="director" class="form-control bg-black text-light border-secondary" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Gatunek filmowy *</label>
                                    <select name="idft" class="form-select bg-black text-light border-secondary" required>
                                        <option value="">-- Wybierz gatunek --</option>
                                        <?php foreach ($genres as $g): ?>
                                            <option value="<?php echo $g['idft']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Plik wideo (MP4) *</label>
                                    <input type="file" name="film_file" accept=".mp4" class="form-control bg-black text-light border-secondary" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Okładka / Plakat (opcjonalnie, JPG/PNG)</label>
                                    <input type="file" name="cover_file" accept="image/*" class="form-control bg-black text-light border-secondary">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Napisy / Opis (opcjonalnie)</label>
                                    <textarea name="subtitles" rows="3" class="form-control bg-black text-light border-secondary"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger w-100 fw-bold">Zapisz film</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-5 mb-4">
                        <div class="p-4 bg-dark border border-secondary rounded-3">
                            <h5 class="text-info mb-3">📋 Utwórz nową playlistę</h5>
                            <form action="index.php" method="post">
                                <input type="hidden" name="action" value="create_playlist">
                                <div class="mb-3">
                                    <label class="form-label small">Nazwa playlisty *</label>
                                    <input type="text" name="playlist_name" class="form-control bg-black text-light border-secondary" required>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_public" id="pubCheck" value="1">
                                    <label class="form-check-label small" for="pubCheck">Publiczna (widoczna dla wszystkich)</label>
                                </div>
                                <button type="submit" class="btn btn-info w-100 fw-bold text-dark">Stwórz playlistę</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php require_once 'footer.php'; ?>

    <script>
        const allFilmsDataset = <?php echo json_encode($all_films); ?>;
        const playlistMapDataset = <?php echo json_encode($playlist_map); ?>;

        let queue = [];
        let currentIndex = 0;
        let isShuffle = false;
        let repeatMode = 0; // 0: OFF, 1: REPEAT ALL, 2: REPEAT ONE

        const video = document.getElementById('videoEngine');
        const videoPlaceholder = document.getElementById('videoPlaceholder');
        const btnPlayPause = document.getElementById('btnPlayPause');
        const svgPlay = document.getElementById('svgPlay');
        const svgPause = document.getElementById('svgPause');
        const btnPrev = document.getElementById('btnPrev');
        const btnNext = document.getElementById('btnNext');
        const btnShuffle = document.getElementById('btnShuffle');
        const btnRepeat = document.getElementById('btnRepeat');
        const svgRepeatAll = document.getElementById('svgRepeatAll');
        const svgRepeatOne = document.getElementById('svgRepeatOne');
        const btnMute = document.getElementById('btnMute');
        const svgVolume = document.getElementById('svgVolume');
        const svgMute = document.getElementById('svgMute');
        const volumeSlider = document.getElementById('volumeSlider');
        let lastVolume = 1;
        const timeSlider = document.getElementById('timeSlider');
        const currentTimeText = document.getElementById('currentTimeText');
        const durationText = document.getElementById('durationText');
        const playerTitle = document.getElementById('playerTitle');
        const playerArtist = document.getElementById('playerArtist');
        const statusBadge = document.getElementById('statusBadge');

        function formatTime(seconds) {
            if (isNaN(seconds) || seconds <= 0) return "0:00";
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ":" + (secs < 10 ? "0" : "") + secs;
        }

        function updatePlayPauseUI(isPlaying) {
            if (isPlaying) {
                svgPlay.classList.add('d-none');
                svgPause.classList.remove('d-none');
            } else {
                svgPause.classList.add('d-none');
                svgPlay.classList.remove('d-none');
            }
        }

        function loadTrack(index) {
            if (queue.length === 0 || index < 0 || index >= queue.length) return;

            currentIndex = index;
            const track = queue[currentIndex];

            video.src = track.src;
            video.style.display = 'block';
            if (videoPlaceholder) videoPlaceholder.style.display = 'none';

            playerTitle.innerText = track.title;
            playerArtist.innerText = "Reżyser: " + track.director + " (" + track.genre_name + ")";
            
            timeSlider.disabled = false;
            timeSlider.value = 0;
            currentTimeText.innerText = "0:00";

            video.play().then(() => {
                updatePlayPauseUI(true);
            }).catch(e => {
                console.log("Autoplay blocked", e);
            });

            if (queue.length > 1) {
                btnShuffle.disabled = false;
            } else {
                btnShuffle.disabled = true;
                isShuffle = false;
                btnShuffle.classList.remove('active');
            }
        }

        btnPlayPause.addEventListener('click', () => {
            if (!video.src) {
                if (allFilmsDataset.length > 0) {
                    playSingleTrack(0);
                }
                return;
            }

            if (video.paused) {
                video.play();
                updatePlayPauseUI(true);
            } else {
                video.pause();
                updatePlayPauseUI(false);
            }
        });

        btnPrev.addEventListener('click', () => {
            if (!video.src) return;

            if (video.currentTime > 3) {
                video.currentTime = 0;
            } else {
                if (isShuffle) {
                    let randIndex = Math.floor(Math.random() * queue.length);
                    loadTrack(randIndex);
                } else {
                    let prevIndex = currentIndex - 1;
                    if (prevIndex < 0) {
                        prevIndex = queue.length - 1;
                    }
                    loadTrack(prevIndex);
                }
            }
        });

        btnNext.addEventListener('click', () => {
            playNextTrack();
        });

        function playNextTrack() {
            if (queue.length === 0) return;

            if (isShuffle && queue.length > 1) {
                let randIndex;
                do {
                    randIndex = Math.floor(Math.random() * queue.length);
                } while (randIndex === currentIndex && queue.length > 1);
                loadTrack(randIndex);
            } else {
                let nextIndex = currentIndex + 1;
                if (nextIndex >= queue.length) {
                    if (repeatMode === 1) {
                        loadTrack(0);
                    } else {
                        statusBadge.innerText = "Zakończono odtwarzanie playlisty";
                        updatePlayPauseUI(false);
                    }
                } else {
                    loadTrack(nextIndex);
                }
            }
        }

        btnShuffle.addEventListener('click', () => {
            if (queue.length <= 1) return;
            isShuffle = !isShuffle;
            if (isShuffle) {
                btnShuffle.classList.add('active');
            } else {
                btnShuffle.classList.remove('active');
            }
        });

        btnRepeat.addEventListener('click', () => {
            repeatMode = (repeatMode + 1) % 3;
            if (repeatMode === 0) {
                btnRepeat.classList.remove('active');
                svgRepeatAll.classList.remove('d-none');
                svgRepeatOne.classList.add('d-none');
                btnRepeat.title = "Tryb pętli: Wyłączony";
            } else if (repeatMode === 1) {
                btnRepeat.classList.add('active');
                svgRepeatAll.classList.remove('d-none');
                svgRepeatOne.classList.add('d-none');
                btnRepeat.title = "Tryb pętli: Cała playlista";
            } else if (repeatMode === 2) {
                btnRepeat.classList.add('active');
                svgRepeatAll.classList.add('d-none');
                svgRepeatOne.classList.remove('d-none');
                btnRepeat.title = "Tryb pętli: Jeden film";
            }
        });

        // AUTOMATYCZNE PRZEJŚCIE DO KOLEJNEGO FILMU (WYMÓG INSTUKCJI PUNKT 17c)
        video.addEventListener('ended', () => {
            if (repeatMode === 2) {
                video.currentTime = 0;
                video.play();
            } else {
                playNextTrack();
            }
        });

        video.addEventListener('timeupdate', () => {
            if (!isNaN(video.duration) && video.duration > 0) {
                const progress = (video.currentTime / video.duration) * 100;
                timeSlider.value = progress;
                currentTimeText.innerText = formatTime(video.currentTime);
                durationText.innerText = formatTime(video.duration);
            }
        });

        timeSlider.addEventListener('input', () => {
            if (!isNaN(video.duration) && video.duration > 0) {
                const seekTime = (timeSlider.value / 100) * video.duration;
                video.currentTime = seekTime;
            }
        });

        function updateVolumeBackground(valPercent) {
            volumeSlider.style.background = `linear-gradient(to right, #e50914 ${valPercent}%, #535353 ${valPercent}%)`;
        }
        updateVolumeBackground(100);

        volumeSlider.addEventListener('input', () => {
            const val = volumeSlider.value / 100;
            video.volume = val;
            volumeSlider.title = "Głośność: " + volumeSlider.value + "%";
            updateVolumeBackground(volumeSlider.value);

            if (val === 0) {
                svgVolume.classList.add('d-none');
                svgMute.classList.remove('d-none');
            } else {
                svgMute.classList.add('d-none');
                svgVolume.classList.remove('d-none');
                lastVolume = val;
            }
        });

        btnMute.addEventListener('click', () => {
            if (video.volume > 0) {
                lastVolume = video.volume;
                video.volume = 0;
                volumeSlider.value = 0;
                updateVolumeBackground(0);
                svgVolume.classList.add('d-none');
                svgMute.classList.remove('d-none');
                volumeSlider.title = "Głośność: 0%";
            } else {
                video.volume = lastVolume > 0 ? lastVolume : 1;
                const perc = Math.round(video.volume * 100);
                volumeSlider.value = perc;
                updateVolumeBackground(perc);
                svgMute.classList.add('d-none');
                svgVolume.classList.remove('d-none');
                volumeSlider.title = "Głośność: " + perc + "%";
            }
        });

        function playSingleTrack(index) {
            queue = [allFilmsDataset[index]];
            statusBadge.innerText = "Odtwarzanie pojedynczego filmu";
            loadTrack(0);
        }

        function playPlaylist(idpl, playlistName) {
            if (playlistMapDataset[idpl] && playlistMapDataset[idpl].length > 0) {
                queue = playlistMapDataset[idpl];
                statusBadge.innerText = "Playlista: " + playlistName;
                loadTrack(0);
            } else {
                alert("Ta playlista jest pusta!");
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>