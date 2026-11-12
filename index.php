<?php
/* =====================================================================
 *  index.php — 첫 화면
 *  관리자 화면(admin.php)에서 등록한 앱을 과목별 탭으로 보여 줍니다.
 * ===================================================================== */
require_once __DIR__ . '/config.php';

// 등록된 앱 (최신 등록 순)
$apps = apps_newest_first();

// 탭 목록: '전체' + 과목들, 그리고 과목별 앱 개수
$tabs   = array_merge(['전체'], SUBJECTS);
$counts = ['전체' => count($apps)];
foreach (SUBJECTS as $s) $counts[$s] = 0;
foreach ($apps as $a) {
    if (isset($counts[$a['subject']])) $counts[$a['subject']]++;
}

render_head(SITE_NAME);
render_header();
?>

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
                <span class="ml-auto text-sm text-cocoa-500 font-bold"><span id="shownCount"><?= count($apps) ?></span>개</span>
            </div>

            <!-- 과목 분류 탭 -->
            <div class="flex flex-wrap gap-2 mb-8" role="tablist">
                <?php foreach ($tabs as $i => $tab): ?>
                    <button type="button" role="tab"
                            data-tab="<?= h($tab) ?>"
                            class="tab-btn px-5 py-2 rounded-full font-bold border-2 transition
                                   <?= $i === 0
                                       ? 'bg-teal-600 border-teal-600 text-white shadow'
                                       : 'bg-white border-cocoa-200 text-cocoa-700 hover:border-teal-400 hover:text-teal-700' ?>">
                        <?= h($tab) ?>
                        <span class="ml-1 text-xs opacity-70"><?= $counts[$tab] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (empty($apps)): ?>
                <!-- 등록된 앱이 하나도 없을 때 -->
                <div class="bg-white border-2 border-dashed border-cocoa-200 rounded-3xl py-16 text-center">
                    <div class="text-5xl mb-3">🧩</div>
                    <p class="text-cocoa-600 font-bold">앱이 곧 여기에 모일 거예요.</p>
                    <p class="text-cocoa-400 text-sm mt-1">조금만 기다려 주세요!</p>
                </div>
            <?php else: ?>
                <!-- 앱 카드 목록 -->
                <div id="appGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($apps as $app): $thumb = thumb_url($app); ?>
                        <a href="<?= h(app_href($app)) ?>" target="_blank" rel="noopener noreferrer"
                           data-subject="<?= h($app['subject']) ?>"
                           class="app-card group bg-white rounded-3xl overflow-hidden shadow-sm border border-cocoa-100
                                  hover:shadow-lg hover:-translate-y-1 hover:border-teal-300 transition flex flex-col">
                            <!-- 썸네일 (없으면 대체 아이콘) -->
                            <div class="aspect-video bg-teal-50 flex items-center justify-center overflow-hidden">
                                <?php if ($thumb): ?>
                                    <img src="<?= h($thumb) ?>" alt="<?= h($app['title']) ?> 썸네일"
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                                <?php else: ?>
                                    <span class="text-6xl group-hover:scale-110 transition"><?= h($app['emoji'] ?: '🧩') ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- 글 영역 -->
                            <div class="p-5 flex-1 flex flex-col">
                                <span class="self-start text-xs font-bold px-2.5 py-1 rounded-full mb-2 <?= subject_badge($app['subject']) ?>">
                                    <?= h($app['subject']) ?>
                                </span>
                                <h3 class="text-lg font-extrabold text-cocoa-800 mb-1"><?= h($app['title']) ?></h3>
                                <p class="text-sm text-cocoa-500 leading-relaxed flex-1"><?= nl2br(h($app['desc'])) ?></p>
                                <p class="mt-4 text-sm font-bold text-teal-600 group-hover:text-teal-700">열어 보기 ↗</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- 선택한 과목에 앱이 없을 때 -->
                <div id="emptyTab" class="hidden bg-white border-2 border-dashed border-cocoa-200 rounded-3xl py-16 text-center">
                    <div class="text-5xl mb-3">🌱</div>
                    <p class="text-cocoa-600 font-bold">이 과목에는 아직 앱이 없어요.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // 과목 탭을 누르면 그 과목의 카드만 보이게 하기
        const tabBtns  = document.querySelectorAll('.tab-btn');
        const cards    = document.querySelectorAll('.app-card');
        const emptyTab = document.getElementById('emptyTab');
        const shown    = document.getElementById('shownCount');

        const ON  = ['bg-teal-600', 'border-teal-600', 'text-white', 'shadow'];
        const OFF = ['bg-white', 'border-cocoa-200', 'text-cocoa-700', 'hover:border-teal-400', 'hover:text-teal-700'];

        function selectTab(name) {
            // 탭 버튼 색 바꾸기
            tabBtns.forEach(btn => {
                const active = btn.dataset.tab === name;
                btn.classList.remove(...(active ? OFF : ON));
                btn.classList.add(...(active ? ON : OFF));
                btn.setAttribute('aria-selected', active);
            });
            // 카드 보이기·숨기기
            let n = 0;
            cards.forEach(card => {
                const show = name === '전체' || card.dataset.subject === name;
                card.classList.toggle('hidden', !show);
                if (show) n++;
            });
            if (shown) shown.textContent = n;
            if (emptyTab) emptyTab.classList.toggle('hidden', n > 0);
            // 주소 끝에 #수학 처럼 남겨서 새로고침해도 같은 탭 유지
            history.replaceState(null, '', name === '전체' ? location.pathname : '#' + encodeURIComponent(name));
        }

        tabBtns.forEach(btn => btn.addEventListener('click', () => selectTab(btn.dataset.tab)));

        // 처음 열 때 주소에 #과목 이 있으면 그 탭으로
        const fromHash = decodeURIComponent(location.hash.slice(1));
        if ([...tabBtns].some(b => b.dataset.tab === fromHash)) selectTab(fromHash);
    </script>

<?php render_footer(); ?>
