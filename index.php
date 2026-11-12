<?php
// ===== 기본 설정 =====
$siteEmoji = '🌿';
$siteName  = '지영샘의 온라인 바이브';

// 위쪽 메뉴 (주소는 나중에 연결하세요. 예: 'notice.php')
$menus = [
    ['name' => '공지사항', 'icon' => '📢', 'url' => '#'],
    ['name' => '자료실',   'icon' => '📁', 'url' => '#'],
];

// 앱 목록 (여기에 추가하면 '전체 앱 모아보기'에 카드로 나타납니다)
// 예: ['emoji' => '🎡', 'title' => '엄지샘 돌림판', 'desc' => '상점·벌점 돌림판', 'url' => 'wheel.php'],
$apps = [];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>

    <!-- 나눔스퀘어라운드 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/innks/NanumSquareRound@master/nanumsquareround.min.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        round: ['NanumSquareRound', 'sans-serif'],
                    },
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

    <!-- ===== 위쪽 메뉴 ===== -->
    <header class="bg-teal-700 text-white shadow-md sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <a href="index.php" class="flex items-center gap-2 text-lg sm:text-xl font-extrabold tracking-tight">
                    <span class="text-2xl"><?= $siteEmoji ?></span>
                    <span><?= htmlspecialchars($siteName) ?></span>
                </a>

                <!-- 넓은 화면 메뉴 -->
                <nav class="hidden sm:flex items-center gap-1">
                    <?php foreach ($menus as $m): ?>
                        <a href="<?= htmlspecialchars($m['url']) ?>"
                           class="px-4 py-2 rounded-full font-bold hover:bg-teal-600 transition">
                            <?= $m['icon'] ?> <?= htmlspecialchars($m['name']) ?>
                        </a>
                    <?php endforeach; ?>
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
                <?php foreach ($menus as $m): ?>
                    <a href="<?= htmlspecialchars($m['url']) ?>"
                       class="block px-4 py-2 rounded-lg font-bold hover:bg-teal-600">
                        <?= $m['icon'] ?> <?= htmlspecialchars($m['name']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <!-- ===== 본문 ===== -->
    <main class="flex-1">
        <!-- 인사말 띠 -->
        <section class="bg-gradient-to-b from-teal-100 to-cocoa-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-14 text-center">
                <p class="text-teal-700 font-bold mb-2">어서 와요! 👋</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-cocoa-800">
                    오늘도 즐겁게 배워 볼까요?
                </h1>
            </div>
        </section>

        <!-- 전체 앱 모아보기 -->
        <section class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-1.5 h-8 bg-cocoa-500 rounded-full"></span>
                <h2 class="text-2xl font-extrabold text-teal-800">전체 앱 모아보기</h2>
                <span class="ml-auto text-sm text-cocoa-500 font-bold"><?= count($apps) ?>개</span>
            </div>

            <?php if (empty($apps)): ?>
                <div class="bg-white border-2 border-dashed border-cocoa-200 rounded-3xl py-16 text-center">
                    <div class="text-5xl mb-3">🧩</div>
                    <p class="text-cocoa-600 font-bold">앱이 곧 여기에 모일 거예요.</p>
                    <p class="text-cocoa-400 text-sm mt-1">조금만 기다려 주세요!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($apps as $app): ?>
                        <a href="<?= htmlspecialchars($app['url']) ?>"
                           class="group bg-white rounded-3xl p-6 shadow-sm border border-cocoa-100
                                  hover:shadow-lg hover:-translate-y-1 hover:border-teal-300 transition">
                            <div class="w-14 h-14 rounded-2xl bg-teal-50 flex items-center justify-center text-3xl mb-4
                                        group-hover:bg-teal-100 transition">
                                <?= $app['emoji'] ?>
                            </div>
                            <h3 class="text-lg font-extrabold text-cocoa-800 mb-1"><?= htmlspecialchars($app['title']) ?></h3>
                            <p class="text-sm text-cocoa-500"><?= htmlspecialchars($app['desc']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- ===== 아래쪽 ===== -->
    <footer class="bg-cocoa-800 text-cocoa-200 text-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 text-center">
            © <?= date('Y') ?> <?= $siteEmoji ?> <?= htmlspecialchars($siteName) ?>
        </div>
    </footer>

    <script>
        const btn = document.getElementById('menuBtn');
        const menu = document.getElementById('mobileMenu');
        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
            menu.classList.toggle('flex');
        });
    </script>
</body>
</html>
