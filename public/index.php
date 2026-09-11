<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08131f">
    <title>Cybermon: POKEMON ARENA</title>
    <link rel="preload" href="/assets/fonts/vt323-regular.woff" as="font" type="font/woff" crossorigin>
    <link rel="stylesheet" href="/assets/world.css?v=11-font">
    <script src="/assets/game.js?v=11-font" defer></script>
</head>
<body>
    <div class="scanlines" aria-hidden="true"></div>

    <header class="site-header">
        <a class="club-mark" href="/" aria-label="Cybermon POKEMON ARENA">
            <span class="club-shield">C</span>
            <span>
                <strong>CYBERMON</strong>
                <small>POKEMON ARENA · SRC - Security Research Club</small>
            </span>
        </a>

        <nav class="header-actions" aria-label="Game controls">
            <button id="saveButton" class="icon-button" type="button">⇩ Lưu game</button>
            <button id="loadButton" class="icon-button" type="button">⇧ Nạp game</button>
            <button id="musicButton" class="icon-button" type="button" aria-pressed="false">♫ Nhạc</button>
            <button id="resetButton" class="icon-button danger" type="button">↻ Chơi lại</button>
        </nav>
    </header>

    <main class="page-shell">
        <ol id="stageTrack" class="stage-track" aria-label="Tiến trình ba màn">
            <li data-stage="1"><span>01</span><div><b>Khởi động</b><small>TACTICS</small></div></li>
            <li data-stage="2"><span>02</span><div><b>Counter</b><small>ENDURANCE</small></div></li>
            <li data-stage="3"><span>03</span><div><b>BOSS</b><small>PARADOX</small></div></li>
        </ol>

        <section class="game-grid" aria-live="polite">
            <article class="arena-panel">
                <div class="arena-heading">
                    <div>
                        <span id="levelBadge" class="level-badge">LEVEL 1 · TACTICS</span>
                        <h1 id="levelTitle">Đồng Cỏ Khởi Động</h1>
                        <p id="levelStory">Hạ 6 đối thủ bằng chiến thuật và quản lý HP.</p>
                    </div>
                </div>

                <div id="worldShell" class="world-shell stage-1">
                    <div id="mapView" class="map-view">
                        <canvas id="worldCanvas" tabindex="0" aria-label="Bản đồ Cybermon"></canvas>
                        <div class="keyboard-hint">↑ ↓ ← → &nbsp; DI CHUYỂN</div>
                        <div id="mapPrompt" class="map-prompt">Bấm phím mũi tên để bắt đầu đi.</div>
                    </div>

                    <div id="battleView" class="battle-view" hidden>
                        <div class="battle-banner">
                            <span class="eyebrow">ENCOUNTER</span>
                            <strong>ĐỌC Ý ĐỒ · CHỌN THẾ · GIỮ HP</strong>
                        </div>
                        <p id="battleIntent" class="battle-intent"></p>
                        <div class="combatants">
                            <div class="combatant player-combatant">
                                <img id="battlePlayerSprite" src="/assets/materials/pokemons/charmander.png" alt="Cybermon của người chơi">
                                <div class="combatant-name" id="battlePlayerName">TRAINER</div>
                                <div class="stat-row"><span>HP</span><b id="battlePlayerHP">100</b></div>
                                <div class="stat-row"><span>TYPE</span><b id="battlePlayerType">FIRE</b></div>
                                <div class="stat-row"><span>DAMAGE</span><b id="battlePlayerDamage">62</b></div>
                            </div>
                            <div class="compare-sign">VS</div>
                            <div class="combatant enemy-combatant">
                                <img id="battleEnemySprite" src="/assets/materials/pokemons/pikachu.png" alt="Đối thủ">
                                <div class="combatant-name" id="battleEnemyName">PIKACHU</div>
                                <div class="stat-row"><span>HP</span><b id="battleEnemyHP">—</b></div>
                                <div class="stat-row"><span>TYPE</span><b id="battleEnemyType">ELECTRIC</b></div>
                                <div class="stat-row"><span>DAMAGE</span><b id="battleEnemyDamage">36</b></div>
                                <div id="battleEnemyDebuffRow" class="stat-row debuff-row"><span>DEBUFF</span><b id="battleEnemyDebuff">—</b></div>
                            </div>
                        </div>
                        <p id="battleResult" class="battle-result">Bạn sẵn sàng chưa?</p>
                        <div class="battle-actions">
                            <button id="fightButton" class="primary-button" type="button" data-game-action="true">⚔ ĐÁNH</button>
                            <button id="guardButton" class="secondary-button" type="button" data-game-action="true">THỦ</button>
                            <button id="breakButton" class="secondary-button" type="button" data-game-action="true">PHÁ THỦ</button>
                            <button id="potionButton" class="secondary-button" type="button" data-game-action="true">HỒI PHỤC</button>
                            <button id="runButton" class="secondary-button" type="button" data-game-action="true">↩ CHẠY</button>
                        </div>
                    </div>

                    <div id="defeatView" class="defeat-view" hidden>
                        <span class="kicker">BATTLE LOST</span>
                        <h2>Bạn đã thua!</h2>
                        <p id="defeatMessage">Bạn phải bắt đầu lại từ Màn 1.</p>
                        <button id="retryButton" class="primary-button large" type="button" data-game-action="true">THỬ LẠI TỪ MÀN 1 →</button>
                    </div>

                    <div id="clearView" class="clear-view" hidden>
                        <span id="clearKicker" class="kicker">LEVEL CLEAR</span>
                        <h2 id="clearTitle">Chúc mừng!</h2>
                        <p id="clearMessage">Bạn đã hoàn thành màn chơi.</p>
                        <div id="flagBox" class="flag-box" hidden>
                            <span>VICTORY FLAG</span>
                            <code id="flagValue"></code>
                        </div>
                        <button id="continueButton" class="primary-button large" type="button" data-game-action="true">SANG MÀN TIẾP →</button>
                    </div>
                </div>
            </article>

            <aside class="command-panel">
                <section class="trainer-card">
                    <div>
                        <small>ACTIVE TRAINER</small>
                        <strong id="sideTrainerName">—</strong>
                    </div>
                    <div class="trainer-stats">
                        <span id="sideStarter">—</span>
                        <span id="sideResources">HP 100 · BÌNH 3</span>
                        <span><b id="sideDamage">—</b> DMG</span>
                    </div>
                </section>

                <section class="mission-card">
                    <span class="eyebrow">NHIỆM VỤ</span>
                    <p id="missionText">Bắt đầu game để nhận nhiệm vụ.</p>
                    <div id="progressText" class="progress-text">—</div>
                </section>

                <section class="control-card">
                    <span class="eyebrow">HƯỚNG DẪN</span>
                    <div id="actionArea" class="action-area"></div>
                </section>

                <section class="battle-log">
                    <span class="eyebrow">BATTLE LOG</span>
                    <ul id="battleLog"><li>Đấu trường đang chờ người chơi.</li></ul>
                </section>
            </aside>
        </section>
    </main>

    <section id="startOverlay" class="start-overlay">
        <div class="start-card">
            <span class="kicker">SRC - Security Research Club</span>
            <h2>CYBERMON<br><em>Pokemon Arena</em></h2>
            <p>Hãy trở thành master pokemon bằng cách vượt qua các trận đấu pokemon.</p>

            <form id="startForm">
                <label for="nameInput">Tên Trainer</label>
                <input id="nameInput" name="name" maxlength="20" autocomplete="off" placeholder="Ví dụ: sp4kl3" required>

                <fieldset>
                    <legend>Chọn Pokemon</legend>
                    <div class="starter-grid">
                        <button class="starter-option selected" type="button" data-starter="charmander">
                            <img src="/assets/materials/pokemons/charmander.png" alt="">
                            <span>Charmander · FIRE</span>
                        </button>
                        <button class="starter-option" type="button" data-starter="bulbasaur">
                            <img src="/assets/materials/pokemons/bulbasaur.png" alt="">
                            <span>Bulbasaur · GRASS</span>
                        </button>
                        <button class="starter-option" type="button" data-starter="squirtle">
                            <img src="/assets/materials/pokemons/squirtle.png" alt="">
                            <span>Squirtle · WATER</span>
                        </button>
                    </div>
                </fieldset>

                <button class="primary-button large" type="submit">BẮT ĐẦU THỬ THÁCH →</button>
            </form>
            <small class="start-note">Không cần tài khoản · Save/Load luôn có ở thanh trên</small>
        </div>
    </section>

    <input id="saveInput" type="file" accept=".sav,application/octet-stream" hidden>
    <audio id="backgroundMusic" loop preload="none" src="/assets/materials/AnvilleTown.mp3"></audio>
    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <noscript>Game cần JavaScript để hoạt động.</noscript>
</body>
</html>
