<?php
/* =====================================================================
 *  config.php — 모든 페이지가 함께 쓰는 설정과 기능
 *  (index.php, admin.php 맨 위에서 require_once 로 불러옵니다)
 * ===================================================================== */

// 이 파일을 주소창에서 직접 열면 아무것도 보여 주지 않음
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

/* ---------------------------------------------------------------------
 *  1. 기본 설정 (필요하면 여기만 고치세요)
 * --------------------------------------------------------------------- */

// 홈페이지 이름과 이모지
define('SITE_EMOJI', '🌿');
define('SITE_NAME',  '지영샘의 온라인 바이브');

// ★ 관리자 비밀번호 — 서버에 올리기 전에 꼭 바꾸세요!
define('ADMIN_PASSWORD', 'change-me-1234');

// 과목 분류 (첫 화면 탭과 관리자 화면 선택 목록에 똑같이 쓰입니다)
// '전체' 탭은 자동으로 맨 앞에 붙으므로 여기에는 넣지 않습니다.
const SUBJECTS = ['국어', '수학', '과학', '영어', '업무'];

// 과목별 배지 색 (Tailwind 클래스) — 목록에 없는 과목은 기본 색
const SUBJECT_COLORS = [
    '국어' => 'bg-rose-100 text-rose-700',
    '수학' => 'bg-sky-100 text-sky-700',
    '과학' => 'bg-emerald-100 text-emerald-700',
    '영어' => 'bg-amber-100 text-amber-800',
    '업무' => 'bg-cocoa-100 text-cocoa-700',
];

// 위쪽 메뉴 (아직 연결 전이라 '#' — 나중에 파일 이름으로 바꾸세요)
const MENUS = [
    ['name' => '공지사항', 'icon' => '📢', 'url' => '#'],
    ['name' => '자료실',   'icon' => '📁', 'url' => '#'],
];

// 폴더와 파일 위치
define('BASE_DIR',    __DIR__);
define('DATA_DIR',    BASE_DIR . '/data');
define('UPLOAD_DIR',  BASE_DIR . '/uploads');
define('APPS_FILE',   DATA_DIR . '/apps.json');
define('UPLOAD_URL',  'uploads/');               // 브라우저에서 보는 썸네일 주소 앞부분

// 썸네일 업로드 제한
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);     // 2MB
const ALLOWED_IMAGE_TYPES = [                    // 실제 파일 내용(MIME) => 저장할 확장자
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

// 한국 시간
date_default_timezone_set('Asia/Seoul');

// 세션 시작 (관리자 로그인 유지용)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/* ---------------------------------------------------------------------
 *  2. 폴더·파일 자동 준비
 *     data, uploads 폴더와 apps.json 이 없으면 만들고,
 *     주소창 접근·PHP 실행을 막는 .htaccess 도 함께 만듭니다.
 * --------------------------------------------------------------------- */
function setup_storage(): void
{
    // data 폴더: 주소창으로 들어와도 열리지 않게 전부 차단
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $dataHtaccess = DATA_DIR . '/.htaccess';
    if (!file_exists($dataHtaccess)) {
        file_put_contents($dataHtaccess,
            "# data 폴더는 웹에서 접근 금지\n" .
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n" .
            "Options -Indexes\n"
        );
    }
    // .htaccess 가 안 먹는 서버를 대비해 빈 index.html 도 둠 (폴더 목록 노출 방지)
    if (!file_exists(DATA_DIR . '/index.html')) {
        file_put_contents(DATA_DIR . '/index.html', '');
    }

    // apps.json: 없으면 빈 목록으로 만듦
    if (!file_exists(APPS_FILE)) {
        file_put_contents(APPS_FILE, "[]", LOCK_EX);
    }

    // uploads 폴더: 이미지는 보이되, PHP 같은 스크립트는 실행되지 않게 막음
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $upHtaccess = UPLOAD_DIR . '/.htaccess';
    if (!file_exists($upHtaccess)) {
        file_put_contents($upHtaccess,
            "# uploads 폴더에서는 스크립트 실행 금지, 이미지 파일만 허용\n" .
            "Options -Indexes -ExecCGI\n" .
            "<IfModule mod_mime.c>\n" .
            "    RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht .cgi .pl .py\n" .
            "    RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht\n" .
            "</IfModule>\n" .
            "# 이미지가 아닌 파일은 아예 열리지 않게 함\n" .
            "<FilesMatch \"(?i)\\.(?!(jpe?g|png|gif|webp)$)[^.]+$\">\n" .
            "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n" .
            "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n" .
            "</FilesMatch>\n"
        );
    }
    if (!file_exists(UPLOAD_DIR . '/index.html')) {
        file_put_contents(UPLOAD_DIR . '/index.html', '');
    }
}
setup_storage();

/* ---------------------------------------------------------------------
 *  3. 앱 데이터 읽기·쓰기 (apps.json)
 *     앱 하나의 모양:
 *     { id, title, subject, desc, url, thumb, emoji, created_at, updated_at }
 * --------------------------------------------------------------------- */

// 전체 앱 목록 읽기
function load_apps(): array
{
    $json = @file_get_contents(APPS_FILE);
    $apps = json_decode($json ?: '[]', true);
    return is_array($apps) ? $apps : [];
}

// 전체 앱 목록 저장 (동시에 저장해도 깨지지 않게 잠금)
function save_apps(array $apps): bool
{
    $json = json_encode(array_values($apps), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return file_put_contents(APPS_FILE, $json, LOCK_EX) !== false;
}

// id 로 앱 하나 찾기 (없으면 null)
function find_app(int $id): ?array
{
    foreach (load_apps() as $app) {
        if ((int)$app['id'] === $id) return $app;
    }
    return null;
}

// 새 앱에 줄 번호(id) 만들기: 지금까지 가장 큰 번호 + 1
function next_app_id(array $apps): int
{
    $max = 0;
    foreach ($apps as $a) $max = max($max, (int)$a['id']);
    return $max + 1;
}

// 최신 등록 순으로 정렬해서 돌려주기
function apps_newest_first(): array
{
    $apps = load_apps();
    usort($apps, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
    return $apps;
}

/* ---------------------------------------------------------------------
 *  4. 연결 주소 처리
 *     - https:// 또는 http:// 로 시작하면 그 주소 그대로
 *     - game.html 처럼 파일 이름만 넣으면 내 홈페이지 안의 파일
 * --------------------------------------------------------------------- */

// 입력한 주소가 올바른지 검사. 올바르면 정리된 주소, 아니면 null
function normalize_app_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') return null;

    // 전체 주소
    if (preg_match('#^https?://#i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    // 내 홈페이지의 파일: 글자·숫자·한글·- _ . / 만 허용
    // (javascript: 같은 위험한 주소, ../ 로 폴더 밖 나가기, // 로 시작하는 주소는 거부)
    $url = ltrim($url, './');
    if ($url === ''
        || strpos($url, '..') !== false
        || strpos($url, '//') !== false
        || !preg_match('#^[\p{L}\p{N}_\-./]+$#u', $url)) {
        return null;
    }
    return $url;
}

// 화면에 링크로 넣을 주소 (파일 이름이면 그대로 두어 내 홈페이지 기준으로 열림)
function app_href(array $app): string
{
    return $app['url'];
}

// 외부 주소인지 확인 (관리 목록에 표시용)
function is_external_url(string $url): bool
{
    return (bool)preg_match('#^https?://#i', $url);
}

/* ---------------------------------------------------------------------
 *  5. 썸네일 업로드
 * --------------------------------------------------------------------- */

// 업로드된 썸네일을 검사하고 uploads 폴더에 저장.
// 성공: ['ok'=>true, 'file'=>'저장된파일이름'] / 실패: ['ok'=>false, 'error'=>'이유']
// 파일을 고르지 않았으면: ['ok'=>true, 'file'=>null]
function handle_thumbnail_upload(string $field): array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'file' => null];
    }
    $f = $_FILES[$field];

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => '이미지는 2MB까지만 올릴 수 있어요.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['ok' => false, 'error' => '이미지를 올리는 중 문제가 생겼어요. 다시 시도해 주세요.'];
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => '이미지는 2MB까지만 올릴 수 있어요.'];
    }

    // 파일 이름의 확장자가 아니라 "실제 내용"으로 이미지인지 확인
    $info = @getimagesize($f['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $real = finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
        if ($real !== $mime) $mime = '';   // 두 검사 결과가 다르면 거부
    }
    if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
        return ['ok' => false, 'error' => 'jpg, png, gif, webp 이미지 파일만 올릴 수 있어요.'];
    }

    // 원래 이름은 버리고 겹치지 않는 새 이름으로 저장
    $name = 'thumb_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ALLOWED_IMAGE_TYPES[$mime];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        return ['ok' => false, 'error' => '이미지를 저장하지 못했어요. uploads 폴더 권한을 확인해 주세요.'];
    }
    @chmod(UPLOAD_DIR . '/' . $name, 0644);
    return ['ok' => true, 'file' => $name];
}

// 썸네일 파일 지우기 (uploads 폴더 안의 파일만)
function delete_thumbnail(?string $file): void
{
    if (!$file) return;
    $path = UPLOAD_DIR . '/' . basename($file);
    if (is_file($path)) @unlink($path);
}

// 썸네일 이미지 주소 (없으면 null)
function thumb_url(array $app): ?string
{
    if (empty($app['thumb'])) return null;
    $file = basename($app['thumb']);
    return is_file(UPLOAD_DIR . '/' . $file) ? UPLOAD_URL . rawurlencode($file) : null;
}

/* ---------------------------------------------------------------------
 *  6. 관리자 로그인 · 보안 도우미
 * --------------------------------------------------------------------- */

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function admin_login(string $password): bool
{
    if (hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        return true;
    }
    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

// 폼 위조 방지 토큰 (관리자 폼마다 숨겨서 넣음)
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string)$_POST['csrf']);
}

// 한 번만 보여 주는 안내 메시지 (저장 완료 등)
function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// 화면에 글자를 안전하게 출력 (HTML 태그가 실행되지 않게)
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// 과목 배지 색
function subject_badge(string $subject): string
{
    return SUBJECT_COLORS[$subject] ?? 'bg-teal-100 text-teal-700';
}

/* ---------------------------------------------------------------------
 *  7. 공통 화면 조각 (<head>, 위쪽 메뉴, 아래쪽)
 *     두 페이지의 디자인을 똑같이 유지하려고 한곳에 모았습니다.
 * --------------------------------------------------------------------- */

function render_head(string $title): void
{ ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?></title>

    <!-- 나눔스퀘어라운드 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/innks/NanumSquareRound@master/nanumsquareround.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { round: ['NanumSquareRound', 'sans-serif'] },
                    colors: {
                        teal: {
                            50: '#effaf8', 100: '#d6f1ec', 200: '#aee2d9', 300: '#7dcdc0',
                            400: '#4fb2a4', 500: '#35978a', 600: '#2a7a70', 700: '#24625b',
                            800: '#204f4a', 900: '#1d423e',
                        },
                        cocoa: {
                            50: '#faf6f2', 100: '#f2e9df', 200: '#e3d0bd', 300: '#cfb093',
                            400: '#b98f6c', 500: '#a07553', 600: '#855e43', 700: '#6b4a37',
                            800: '#563c2f', 900: '#463229',
                        },
                    },
                },
            },
        };
    </script>
</head>
<body class="font-round bg-cocoa-50 text-cocoa-900 min-h-screen flex flex-col">
<?php }

function render_header(): void
{ ?>
    <!-- ===== 위쪽 메뉴 ===== -->
    <header class="bg-teal-700 text-white shadow-md sticky top-0 z-20">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <a href="index.php" class="flex items-center gap-2 text-lg sm:text-xl font-extrabold tracking-tight">
                    <span class="text-2xl"><?= SITE_EMOJI ?></span>
                    <span><?= h(SITE_NAME) ?></span>
                </a>

                <!-- 넓은 화면 메뉴 -->
                <nav class="hidden sm:flex items-center gap-1">
                    <?php foreach (MENUS as $m): ?>
                        <a href="<?= h($m['url']) ?>"
                           class="px-4 py-2 rounded-full font-bold hover:bg-teal-600 transition">
                            <?= $m['icon'] ?> <?= h($m['name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <!-- 관리자 버튼 (오른쪽 끝) -->
                    <a href="admin.php"
                       class="ml-2 px-4 py-2 rounded-full font-bold bg-cocoa-500 hover:bg-cocoa-400 transition">
                        🔐 관리자
                    </a>
                </nav>

                <!-- 좁은 화면 메뉴 버튼 -->
                <button id="menuBtn" class="sm:hidden p-2 rounded-lg hover:bg-teal-600" aria-label="메뉴 열기">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

            <!-- 좁은 화면 메뉴 -->
            <nav id="mobileMenu" class="hidden sm:hidden pb-3 flex-col gap-1">
                <?php foreach (MENUS as $m): ?>
                    <a href="<?= h($m['url']) ?>" class="block px-4 py-2 rounded-lg font-bold hover:bg-teal-600">
                        <?= $m['icon'] ?> <?= h($m['name']) ?>
                    </a>
                <?php endforeach; ?>
                <a href="admin.php" class="block px-4 py-2 rounded-lg font-bold bg-cocoa-500 hover:bg-cocoa-400">
                    🔐 관리자
                </a>
            </nav>
        </div>
    </header>
<?php }

function render_footer(): void
{ ?>
    <!-- ===== 아래쪽 ===== -->
    <footer class="bg-cocoa-800 text-cocoa-200 text-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 text-center">
            © <?= date('Y') ?> <?= SITE_EMOJI ?> <?= h(SITE_NAME) ?>
        </div>
    </footer>

    <script>
        // 좁은 화면에서 메뉴 열고 닫기
        const menuBtn = document.getElementById('menuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        menuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            mobileMenu.classList.toggle('flex');
        });
    </script>
</body>
</html>
<?php }
