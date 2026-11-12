<?php
/* =====================================================================
 *  admin.php — 관리자 화면
 *  앱 등록 · 수정 · 삭제 (비밀번호는 config.php 의 ADMIN_PASSWORD)
 * ===================================================================== */
require_once __DIR__ . '/config.php';

// 입력칸 기본값 (새 앱 등록용 빈 양식)
$emptyForm = ['id' => 0, 'title' => '', 'subject' => SUBJECTS[0], 'desc' => '', 'url' => '', 'thumb' => '', 'emoji' => '🧩'];
$form   = $emptyForm;
$errors = [];

/* ---------------------------------------------------------------------
 *  1. 버튼을 눌러 보낸 요청(POST) 처리
 * --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 서버 허용 용량보다 큰 파일을 보내면 PHP가 내용을 통째로 비워 버림 → 안내
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('보낸 파일이 너무 커요. 이미지는 2MB 이하로 올려 주세요.', 'error');
        header('Location: admin.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- 로그인 ---
    if ($action === 'login') {
        if (admin_login((string)($_POST['password'] ?? ''))) {
            flash('어서 오세요, 지영샘! 😊');
        } else {
            sleep(1); // 비밀번호 마구 넣어 보기 방지
            flash('비밀번호가 맞지 않아요.', 'error');
        }
        header('Location: admin.php');
        exit;
    }

    // 여기부터는 로그인한 관리자만
    if (!is_admin()) {
        header('Location: admin.php');
        exit;
    }
    if (!csrf_check()) {
        flash('요청이 만료되었어요. 다시 시도해 주세요.', 'error');
        header('Location: admin.php');
        exit;
    }

    // --- 로그아웃 ---
    if ($action === 'logout') {
        admin_logout();
        header('Location: index.php');
        exit;
    }

    // --- 삭제 ---
    if ($action === 'delete') {
        $id   = (int)($_POST['id'] ?? 0);
        $apps = load_apps();
        foreach ($apps as $i => $a) {
            if ((int)$a['id'] === $id) {
                delete_thumbnail($a['thumb'] ?? null);
                unset($apps[$i]);
                save_apps($apps);
                flash('🗑️ "' . $a['title'] . '" 앱을 삭제했어요.');
                break;
            }
        }
        header('Location: admin.php');
        exit;
    }

    // --- 등록 / 수정 저장 ---
    if ($action === 'save') {
        $form = [
            'id'      => (int)($_POST['id'] ?? 0),
            'title'   => trim((string)($_POST['title'] ?? '')),
            'subject' => (string)($_POST['subject'] ?? ''),
            'desc'    => trim((string)($_POST['desc'] ?? '')),
            'url'     => trim((string)($_POST['url'] ?? '')),
            'emoji'   => trim((string)($_POST['emoji'] ?? '')),
            'thumb'   => '',
        ];
        $isEdit = $form['id'] > 0;
        $old    = $isEdit ? find_app($form['id']) : null;
        if ($isEdit && !$old) {
            flash('수정하려는 앱을 찾지 못했어요.', 'error');
            header('Location: admin.php');
            exit;
        }
        $form['thumb'] = $old['thumb'] ?? '';

        // 입력값 검사
        if ($form['title'] === '')                     $errors[] = '앱 이름을 입력해 주세요.';
        if (mb_strlen($form['title']) > 50)            $errors[] = '앱 이름은 50자까지 쓸 수 있어요.';
        if (!in_array($form['subject'], SUBJECTS, true)) $errors[] = '과목을 골라 주세요.';
        if (mb_strlen($form['desc']) > 300)            $errors[] = '설명은 300자까지 쓸 수 있어요.';
        $cleanUrl = normalize_app_url($form['url']);
        if ($cleanUrl === null) {
            $errors[] = '연결 주소를 확인해 주세요. game.html 처럼 파일 이름만 넣거나, https:// 로 시작하는 전체 주소를 넣어 주세요.';
        } else {
            $form['url'] = $cleanUrl;
        }
        if ($form['emoji'] === '')       $form['emoji'] = '🧩';
        if (mb_strlen($form['emoji']) > 8) $errors[] = '대체 아이콘은 이모지 하나만 넣어 주세요.';

        // 입력값이 모두 괜찮을 때만 이미지 저장
        if (!$errors) {
            $up = handle_thumbnail_upload('thumb');
            if (!$up['ok']) {
                $errors[] = $up['error'];
            } elseif ($up['file']) {
                // 새 이미지를 올렸으면 예전 이미지는 지움
                delete_thumbnail($form['thumb']);
                $form['thumb'] = $up['file'];
            } elseif (!empty($_POST['remove_thumb'])) {
                // '썸네일 지우기'에 체크했으면 대체 아이콘으로
                delete_thumbnail($form['thumb']);
                $form['thumb'] = '';
            }
        }

        if (!$errors) {
            $apps = load_apps();
            $now  = date('Y-m-d H:i:s');
            if ($isEdit) {
                foreach ($apps as &$a) {
                    if ((int)$a['id'] === $form['id']) {
                        $a = array_merge($a, $form, ['updated_at' => $now]);
                        break;
                    }
                }
                unset($a);
                $msg = '✏️ "' . $form['title'] . '" 앱을 수정했어요.';
            } else {
                $form['id'] = next_app_id($apps);
                $apps[] = $form + ['created_at' => $now, 'updated_at' => $now];
                $msg = '🎉 "' . $form['title'] . '" 앱을 등록했어요. 첫 화면에서 바로 볼 수 있어요!';
            }
            if (save_apps($apps)) {
                flash($msg);
            } else {
                flash('저장하지 못했어요. data 폴더 권한을 확인해 주세요.', 'error');
            }
            header('Location: admin.php');
            exit;
        }
        // 오류가 있으면 아래에서 입력한 내용을 그대로 다시 보여 줌
    }
}

/* ---------------------------------------------------------------------
 *  2. 수정 버튼을 눌렀을 때 (admin.php?edit=번호)
 * --------------------------------------------------------------------- */
if (is_admin() && !$errors && isset($_GET['edit'])) {
    $found = find_app((int)$_GET['edit']);
    if ($found) {
        $form = array_merge($emptyForm, $found);
    } else {
        flash('수정하려는 앱을 찾지 못했어요.', 'error');
        header('Location: admin.php');
        exit;
    }
}

$isEdit = (int)$form['id'] > 0;
$flash  = take_flash();
$apps   = is_admin() ? apps_newest_first() : [];

// 자주 쓰는 대체 아이콘
$emojiPicks = ['🧩', '🎮', '📝', '📊', '🔢', '🧪', '🔬', '🌍', '📚', '✏️', '🔤', '🗣️', '🎡', '🗂️', '💡', '🏆'];

// 입력칸 공통 스타일
$inputCls = 'w-full rounded-2xl border-2 border-cocoa-200 bg-white px-4 py-3 focus:outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-100 transition';

render_head('관리자 · ' . SITE_NAME);
render_header();
?>

    <main class="flex-1">
        <!-- 제목 띠 -->
        <section class="bg-gradient-to-b from-teal-100 to-cocoa-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-cocoa-800">🔐 관리자 화면</h1>
                <?php if (is_admin()): ?>
                    <a href="index.php" target="_blank"
                       class="ml-auto px-4 py-2 rounded-full bg-white border-2 border-teal-200 text-teal-700 font-bold hover:border-teal-400 transition text-sm">
                        첫 화면 보기 ↗
                    </a>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <button class="px-4 py-2 rounded-full bg-cocoa-200 text-cocoa-800 font-bold hover:bg-cocoa-300 transition text-sm">
                            로그아웃
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">

            <!-- 안내 메시지 -->
            <?php if ($flash): ?>
                <div class="rounded-2xl px-5 py-4 font-bold
                            <?= $flash['type'] === 'error' ? 'bg-rose-50 text-rose-700 border-2 border-rose-200' : 'bg-teal-50 text-teal-800 border-2 border-teal-200' ?>">
                    <?= h($flash['msg']) ?>
                </div>
            <?php endif; ?>

<?php if (!is_admin()): ?>
            <!-- ===== 로그인 ===== -->
            <section class="max-w-md mx-auto bg-white rounded-3xl shadow-sm border border-cocoa-100 p-8">
                <div class="text-center mb-6">
                    <div class="text-5xl mb-2">🔑</div>
                    <h2 class="text-xl font-extrabold text-teal-800">관리자 로그인</h2>
                    <p class="text-sm text-cocoa-500 mt-1">선생님만 들어올 수 있어요.</p>
                </div>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    <input type="password" name="password" required autofocus placeholder="비밀번호"
                           class="<?= $inputCls ?>">
                    <button class="w-full rounded-2xl bg-teal-600 hover:bg-teal-700 text-white font-extrabold py-3 transition">
                        들어가기
                    </button>
                </form>
            </section>

<?php else: ?>
            <?php if (ADMIN_PASSWORD === 'change-me-1234'): ?>
                <div class="rounded-2xl px-5 py-4 bg-amber-50 text-amber-800 border-2 border-amber-200 text-sm font-bold">
                    ⚠️ 관리자 비밀번호가 처음 값 그대로예요. config.php 의 ADMIN_PASSWORD 를 꼭 바꿔 주세요.
                </div>
            <?php endif; ?>

            <!-- ===== 1. 앱 등록 / 수정 양식 ===== -->
            <section id="appForm" class="bg-white rounded-3xl shadow-sm border border-cocoa-100 p-6 sm:p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-1.5 h-8 bg-cocoa-500 rounded-full"></span>
                    <h2 class="text-xl font-extrabold text-teal-800">
                        <?= $isEdit ? '✏️ 앱 수정하기' : '➕ 새 앱 등록' ?>
                    </h2>
                </div>

                <!-- 입력 오류 -->
                <?php if ($errors): ?>
                    <ul class="mb-6 rounded-2xl bg-rose-50 border-2 border-rose-200 text-rose-700 px-5 py-4 space-y-1 text-sm font-bold">
                        <?php foreach ($errors as $e): ?><li>• <?= h($e) ?></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="grid gap-5 md:grid-cols-2">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_BYTES ?>">

                    <!-- 앱 이름 -->
                    <label class="block">
                        <span class="block font-bold mb-2">앱 이름 <span class="text-rose-500">*</span></span>
                        <input type="text" name="title" required maxlength="50" value="<?= h($form['title']) ?>"
                               placeholder="예: 외계 신호 해독 게임" class="<?= $inputCls ?>">
                    </label>

                    <!-- 과목 (첫 화면 탭과 같은 목록) -->
                    <label class="block">
                        <span class="block font-bold mb-2">과목 <span class="text-rose-500">*</span></span>
                        <select name="subject" required class="<?= $inputCls ?>">
                            <?php foreach (SUBJECTS as $s): ?>
                                <option value="<?= h($s) ?>" <?= $form['subject'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <!-- 설명 -->
                    <label class="block md:col-span-2">
                        <span class="block font-bold mb-2">설명</span>
                        <textarea name="desc" rows="3" maxlength="300" placeholder="학생들에게 보여 줄 짧은 소개를 써 주세요."
                                  class="<?= $inputCls ?> resize-y"><?= h($form['desc']) ?></textarea>
                    </label>

                    <!-- 연결 주소 -->
                    <label class="block md:col-span-2">
                        <span class="block font-bold mb-2">연결 주소 <span class="text-rose-500">*</span></span>
                        <input type="text" name="url" id="urlInput" required value="<?= h($form['url']) ?>"
                               placeholder="game.html  또는  https://example.com/app" class="<?= $inputCls ?>">
                        <span id="urlHint" class="block text-sm text-cocoa-500 mt-2">
                            파일 이름만 넣으면 내 홈페이지의 파일로, https:// 로 시작하면 그 주소로 연결돼요.
                        </span>
                    </label>

                    <!-- 썸네일 이미지 -->
                    <div class="block">
                        <span class="block font-bold mb-2">썸네일 이미지 <span class="text-cocoa-400 font-normal text-sm">(선택)</span></span>
                        <div class="flex items-start gap-4">
                            <div id="thumbPreview" class="w-32 aspect-video shrink-0 rounded-xl bg-teal-50 border-2 border-cocoa-100 overflow-hidden flex items-center justify-center text-3xl">
                                <?php if ($isEdit && ($t = thumb_url($form))): ?>
                                    <img src="<?= h($t) ?>" class="w-full h-full object-cover" alt="">
                                <?php else: ?>
                                    <span id="previewEmoji"><?= h($form['emoji']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input type="file" name="thumb" id="thumbInput" accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp"
                                       class="block w-full text-sm text-cocoa-600 file:mr-3 file:rounded-full file:border-0 file:bg-teal-100 file:px-4 file:py-2 file:font-bold file:text-teal-800 hover:file:bg-teal-200">
                                <p class="text-xs text-cocoa-400">jpg, png, gif, webp · 2MB까지</p>
                                <?php if ($isEdit && !empty($form['thumb'])): ?>
                                    <label class="flex items-center gap-2 text-sm text-cocoa-600">
                                        <input type="checkbox" name="remove_thumb" value="1" class="rounded accent-rose-500">
                                        지금 썸네일 지우고 대체 아이콘 쓰기
                                    </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 대체 아이콘 (이모지) -->
                    <div class="block">
                        <span class="block font-bold mb-2">대체 아이콘 <span class="text-cocoa-400 font-normal text-sm">(썸네일이 없을 때)</span></span>
                        <input type="text" name="emoji" id="emojiInput" maxlength="8" value="<?= h($form['emoji']) ?>"
                               class="<?= $inputCls ?> text-2xl text-center !w-24">
                        <div class="flex flex-wrap gap-1 mt-3">
                            <?php foreach ($emojiPicks as $em): ?>
                                <button type="button" class="emoji-pick w-9 h-9 rounded-lg hover:bg-teal-100 text-xl transition"><?= $em ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 버튼 -->
                    <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                        <button class="rounded-2xl bg-teal-600 hover:bg-teal-700 text-white font-extrabold px-8 py-3 shadow transition">
                            <?= $isEdit ? '💾 수정 내용 저장하기' : '➕ 새 앱 등록하기' ?>
                        </button>
                        <?php if ($isEdit): ?>
                            <a href="admin.php" class="rounded-2xl bg-cocoa-100 hover:bg-cocoa-200 text-cocoa-800 font-bold px-6 py-3 transition">
                                취소
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <!-- ===== 2. 등록된 앱 목록 ===== -->
            <section class="bg-white rounded-3xl shadow-sm border border-cocoa-100 p-6 sm:p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-1.5 h-8 bg-cocoa-500 rounded-full"></span>
                    <h2 class="text-xl font-extrabold text-teal-800">📋 등록된 앱 목록</h2>
                    <span class="ml-auto text-sm text-cocoa-500 font-bold"><?= count($apps) ?>개</span>
                </div>

                <?php if (!$apps): ?>
                    <p class="text-center text-cocoa-400 py-10">아직 등록된 앱이 없어요. 위에서 첫 앱을 등록해 보세요!</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[640px]">
                            <thead>
                                <tr class="border-b-2 border-cocoa-100 text-cocoa-500 text-sm">
                                    <th class="py-3 px-2 w-14 text-center">번호</th>
                                    <th class="py-3 px-2 w-28">썸네일</th>
                                    <th class="py-3 px-2">앱 이름</th>
                                    <th class="py-3 px-2 w-24">과목</th>
                                    <th class="py-3 px-2 w-36 text-center">관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = count($apps); foreach ($apps as $app): $thumb = thumb_url($app); ?>
                                    <tr class="border-b border-cocoa-50 hover:bg-cocoa-50/60 <?= (int)$form['id'] === (int)$app['id'] ? 'bg-teal-50' : '' ?>">
                                        <td class="py-3 px-2 text-center font-bold text-cocoa-400"><?= $no-- ?></td>
                                        <td class="py-3 px-2">
                                            <div class="w-24 aspect-video rounded-lg bg-teal-50 overflow-hidden flex items-center justify-center text-2xl">
                                                <?php if ($thumb): ?>
                                                    <img src="<?= h($thumb) ?>" class="w-full h-full object-cover" alt="">
                                                <?php else: ?>
                                                    <?= h($app['emoji'] ?: '🧩') ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2">
                                            <a href="<?= h(app_href($app)) ?>" target="_blank" rel="noopener noreferrer"
                                               class="font-extrabold text-cocoa-800 hover:text-teal-700"><?= h($app['title']) ?> ↗</a>
                                            <div class="text-xs text-cocoa-400 mt-1 break-all">
                                                <?= is_external_url($app['url']) ? '🌐' : '🏠' ?> <?= h($app['url']) ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2">
                                            <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= subject_badge($app['subject']) ?>"><?= h($app['subject']) ?></span>
                                        </td>
                                        <td class="py-3 px-2">
                                            <div class="flex justify-center gap-2">
                                                <a href="admin.php?edit=<?= (int)$app['id'] ?>#appForm"
                                                   class="px-3 py-1.5 rounded-full bg-teal-100 text-teal-800 text-sm font-bold hover:bg-teal-200 transition">수정</a>
                                                <form method="post" onsubmit="return confirm('「<?= h(addslashes($app['title'])) ?>」 앱을 삭제할까요?\n삭제하면 되돌릴 수 없어요.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= (int)$app['id'] ?>">
                                                    <button class="px-3 py-1.5 rounded-full bg-rose-100 text-rose-700 text-sm font-bold hover:bg-rose-200 transition">삭제</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <script>
                // 대체 아이콘 빠른 선택
                const emojiInput = document.getElementById('emojiInput');
                document.querySelectorAll('.emoji-pick').forEach(btn => {
                    btn.addEventListener('click', () => {
                        emojiInput.value = btn.textContent.trim();
                        updateEmojiPreview();
                    });
                });
                function updateEmojiPreview() {
                    const pe = document.getElementById('previewEmoji');
                    if (pe) pe.textContent = emojiInput.value || '🧩';
                }
                emojiInput.addEventListener('input', updateEmojiPreview);

                // 썸네일 미리보기 + 2MB·형식 미리 확인
                const thumbInput = document.getElementById('thumbInput');
                const preview    = document.getElementById('thumbPreview');
                thumbInput.addEventListener('change', () => {
                    const file = thumbInput.files[0];
                    if (!file) return;
                    if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                        alert('jpg, png, gif, webp 이미지만 올릴 수 있어요.');
                        thumbInput.value = '';
                        return;
                    }
                    if (file.size > <?= MAX_UPLOAD_BYTES ?>) {
                        alert('이미지는 2MB까지만 올릴 수 있어요.');
                        thumbInput.value = '';
                        return;
                    }
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.className = 'w-full h-full object-cover';
                    preview.innerHTML = '';
                    preview.appendChild(img);
                });

                // 연결 주소 종류 알려 주기
                const urlInput = document.getElementById('urlInput');
                const urlHint  = document.getElementById('urlHint');
                function updateUrlHint() {
                    const v = urlInput.value.trim();
                    if (!v) {
                        urlHint.textContent = '파일 이름만 넣으면 내 홈페이지의 파일로, https:// 로 시작하면 그 주소로 연결돼요.';
                    } else if (/^https?:\/\//i.test(v)) {
                        urlHint.textContent = '🌐 다른 사이트 주소로 연결돼요.';
                    } else {
                        urlHint.textContent = '🏠 내 홈페이지의 ' + v + ' 파일로 연결돼요.';
                    }
                }
                urlInput.addEventListener('input', updateUrlHint);
                updateUrlHint();
            </script>
<?php endif; ?>
        </div>
    </main>

<?php render_footer(); ?>
