'use strict';

const API_URL = '/api/game.php';
const SAVE_URL = '/api/save-load.php';

const stageCopy = {
    1: {
        badge: 'LEVEL 1 · EASY',
        title: 'Đồng Cỏ Khởi Động',
        story: 'Dùng phím mũi tên để đi quanh map và tìm đủ 4 Wild Pikachu.',
        mission: 'Chạm các biểu tượng ⚡ trên bản đồ. Trong battle, damage lớn hơn sẽ hạ đối thủ bằng một đòn.',
        map: '/assets/materials/maps/map1.png',
        width: 736,
        height: 669,
        start: {x: 0.08, y: 0.84},
    },
    2: {
        badge: 'LEVEL 2 · MEDIUM',
        title: 'Counter',
        story: 'Khám phá và quan sát type, damage trước mỗi trận.',
        mission: 'Tìm đối thủ mà bạn có lợi thế hệ. Cân nhắc debuff và sức mạnh trước khi ra đòn.',
        map: '/assets/materials/maps/map2.png',
        width: 783,
        height: 638,
        start: {x: 0.08, y: 0.84},
    },
    3: {
        badge: 'LEVEL 3 · Hard',
        title: 'BOSS',
        story: 'Boss này có sức mạnh vượt trội so với ngươi, nếu muốn chiến thắng, tìm cách lách qua lỗ hổng và hạ gục boss với sức mạnh tuyệt đối!!!',
        mission: 'Trở thành 1 hacker và giải quyết boss',
        map: '/assets/materials/maps/map3.png',
        width: 805,
        height: 678,
        start: {x: 0.10, y: 0.82},
    },
    4: {
        badge: 'ARENA CLEARED',
        title: 'Chúc mừng hacker lord',
        story: 'Bạn đã hoàn thành đủ ba màn của Cybermon Pokemon Arena',
        mission: 'Đưa victory flag cho thành viên CLB tại quầy để xác nhận kết quả.',
        map: '/assets/materials/maps/map3.png',
        width: 805,
        height: 678,
        start: {x: 0.10, y: 0.82},
    },
};

const typeIcons = {fire: '🔥', water: '💧', grass: '🌿', electric: '⚡', glitch: '☠'};
const elements = {
    startOverlay: document.querySelector('#startOverlay'),
    startForm: document.querySelector('#startForm'),
    nameInput: document.querySelector('#nameInput'),
    stageTrack: document.querySelector('#stageTrack'),
    worldShell: document.querySelector('#worldShell'),
    mapView: document.querySelector('#mapView'),
    canvas: document.querySelector('#worldCanvas'),
    mapPrompt: document.querySelector('#mapPrompt'),
    battleView: document.querySelector('#battleView'),
    battlePlayerSprite: document.querySelector('#battlePlayerSprite'),
    battlePlayerName: document.querySelector('#battlePlayerName'),
    battlePlayerType: document.querySelector('#battlePlayerType'),
    battlePlayerDamage: document.querySelector('#battlePlayerDamage'),
    battleEnemySprite: document.querySelector('#battleEnemySprite'),
    battleEnemyName: document.querySelector('#battleEnemyName'),
    battleEnemyType: document.querySelector('#battleEnemyType'),
    battleEnemyDamage: document.querySelector('#battleEnemyDamage'),
    battleEnemyDebuffRow: document.querySelector('#battleEnemyDebuffRow'),
    battleEnemyDebuff: document.querySelector('#battleEnemyDebuff'),
    battleResult: document.querySelector('#battleResult'),
    fightButton: document.querySelector('#fightButton'),
    runButton: document.querySelector('#runButton'),
    defeatView: document.querySelector('#defeatView'),
    defeatMessage: document.querySelector('#defeatMessage'),
    retryButton: document.querySelector('#retryButton'),
    clearView: document.querySelector('#clearView'),
    clearKicker: document.querySelector('#clearKicker'),
    clearTitle: document.querySelector('#clearTitle'),
    clearMessage: document.querySelector('#clearMessage'),
    continueButton: document.querySelector('#continueButton'),
    flagBox: document.querySelector('#flagBox'),
    flagValue: document.querySelector('#flagValue'),
    levelBadge: document.querySelector('#levelBadge'),
    levelTitle: document.querySelector('#levelTitle'),
    levelStory: document.querySelector('#levelStory'),
    missionText: document.querySelector('#missionText'),
    progressText: document.querySelector('#progressText'),
    sideTrainerName: document.querySelector('#sideTrainerName'),
    sideStarter: document.querySelector('#sideStarter'),
    sideDamage: document.querySelector('#sideDamage'),
    actionArea: document.querySelector('#actionArea'),
    battleLog: document.querySelector('#battleLog'),
    saveButton: document.querySelector('#saveButton'),
    loadButton: document.querySelector('#loadButton'),
    saveInput: document.querySelector('#saveInput'),
    resetButton: document.querySelector('#resetButton'),
    musicButton: document.querySelector('#musicButton'),
    music: document.querySelector('#backgroundMusic'),
    toast: document.querySelector('#toast'),
};

let gameState = null;
let selectedStarter = 'charmander';
let mapStage = 0;
let mapImage = null;
let playerPosition = {x: 0.1, y: 0.8};
let busy = false;
let toastTimer = null;
let battleFocusIndex = 0;
const imageCache = {};

function formatNumber(value) {
    return Number(value || 0).toLocaleString('vi-VN');
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function focusButton(button) {
    if (!button || button.hidden || button.disabled) return;
    try {
        button.focus({preventScroll: true});
    } catch (error) {
        button.focus();
    }
}

function focusBattleAction(index = battleFocusIndex) {
    const buttons = [elements.fightButton, elements.runButton];
    battleFocusIndex = ((index % buttons.length) + buttons.length) % buttons.length;
    focusButton(buttons[battleFocusIndex]);
}

function focusVisibleAction() {
    if (busy || !gameState?.started) return;
    if (gameState.defeat_message && !elements.retryButton.hidden && !elements.retryButton.disabled) {
        focusButton(elements.retryButton);
        return;
    }
    if (gameState.pending_stage && !elements.continueButton.hidden && !elements.continueButton.disabled) {
        focusButton(elements.continueButton);
        return;
    }
    if (gameState.current_enemy && !elements.fightButton.disabled && !elements.runButton.disabled) {
        focusBattleAction(battleFocusIndex);
    }
}

function showToast(message, isError = false) {
    if (!message) return;
    window.clearTimeout(toastTimer);
    elements.toast.textContent = message;
    elements.toast.classList.toggle('error', isError);
    elements.toast.classList.add('visible');
    toastTimer = window.setTimeout(() => elements.toast.classList.remove('visible'), 3300);
}

function setBusy(value) {
    busy = value;
    document.querySelectorAll('[data-game-action]').forEach((button) => {
        button.disabled = value;
    });
    document.body.classList.toggle('is-busy', value);
}

async function postAction(action, extra = {}) {
    if (busy) return null;
    setBusy(true);
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action, ...extra}),
        });
        const payload = await response.json();
        if (payload.state) {
            gameState = payload.state;
            render();
        }
        showToast(payload.message, !payload.ok);
        return {response, payload};
    } catch (error) {
        showToast('Không kết nối được tới game server.', true);
        return null;
    } finally {
        setBusy(false);
        // render() runs while data-game-action buttons are disabled. Defer
        // focus until the busy state is released so keyboard activation also
        // works after a battle or stage transition response.
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(focusVisibleAction);
        } else {
            window.setTimeout(focusVisibleAction, 0);
        }
    }
}

function assetPath(enemy) {
    if (!enemy) return '';
    const folder = enemy.boss ? 'bosses' : 'pokemons';
    return `/assets/materials/${folder}/${enemy.image}`;
}

function getImage(src) {
    if (!imageCache[src]) {
        const image = new Image();
        image.src = src;
        image.onload = drawMap;
        imageCache[src] = image;
    }
    return imageCache[src];
}

function currentEncounterList(stage = Number(gameState?.stage || 1)) {
    if (stage === 1) return gameState?.level1?.encounters || [];
    if (stage === 2) return gameState?.level2?.encounters || [];
    return gameState?.level3?.encounters || [];
}

function setupMap(stage) {
    const copy = stageCopy[stage];
    if (!copy || stage > 3) return;
    mapStage = stage;
    playerPosition = {...copy.start};
    elements.canvas.width = copy.width;
    elements.canvas.height = copy.height;
    mapImage = new Image();
    mapImage.src = copy.map;
    mapImage.onload = drawMap;
    elements.canvas.focus({preventScroll: true});
    drawMap();
}

function drawLabel(ctx, text, x, y, color = '#eaf6ff') {
    ctx.font = '700 12px "Pixelify Sans", monospace';
    const width = ctx.measureText(text).width + 12;
    ctx.fillStyle = 'rgba(4, 13, 22, .86)';
    ctx.fillRect(x - width / 2, y - 18, width, 19);
    ctx.fillStyle = color;
    ctx.textAlign = 'center';
    ctx.fillText(text, x, y - 5);
}

function drawMap() {
    if (!gameState || !mapImage || !mapImage.complete || Number(gameState.stage) > 3) return;
    const ctx = elements.canvas.getContext('2d');
    const width = elements.canvas.width;
    const height = elements.canvas.height;
    ctx.clearRect(0, 0, width, height);
    ctx.drawImage(mapImage, 0, 0, width, height);
    ctx.fillStyle = 'rgba(3, 11, 18, .16)';
    ctx.fillRect(0, 0, width, height);

    currentEncounterList().forEach((enemy) => {
        if (enemy.defeated) return;
        const x = enemy.x * width;
        const y = enemy.y * height;
        const color = enemy.boss ? '#ff5877' : (gameState.stage === 2 ? '#ffd85e' : '#51e5ff');
        ctx.beginPath();
        ctx.arc(x, y, enemy.boss ? 35 : 27, 0, Math.PI * 2);
        ctx.fillStyle = `${color}44`;
        ctx.fill();
        ctx.strokeStyle = color;
        ctx.lineWidth = 3;
        ctx.stroke();
        const image = getImage(assetPath(enemy));
        if (image.complete) {
            const size = enemy.boss ? 78 : 55;
            ctx.drawImage(image, x - size / 2, y - size / 2, size, size);
        }
        const icon = typeIcons[enemy.type] || '◆';
        drawLabel(ctx, `${icon} ${enemy.name}${gameState.stage === 2 ? ` · ${enemy.type.toUpperCase()}` : ''}`, x, y + (enemy.boss ? 55 : 43), color);
    });

    const playerImage = getImage('/assets/materials/moving_user.png');
    const px = playerPosition.x * width;
    const py = playerPosition.y * height;
    if (playerImage.complete) ctx.drawImage(playerImage, px - 34, py - 55, 68, 68);
    ctx.beginPath();
    ctx.arc(px, py + 14, 10, 0, Math.PI * 2);
    ctx.fillStyle = '#51e5ff';
    ctx.fill();
    ctx.strokeStyle = '#07131d';
    ctx.lineWidth = 3;
    ctx.stroke();
}

function movePlayer(direction) {
    if (!gameState || busy || Number(gameState.stage) > 3 || gameState.pending_stage || gameState.current_enemy) return;
    const step = 0.035;
    if (direction === 'up') playerPosition.y -= step;
    if (direction === 'down') playerPosition.y += step;
    if (direction === 'left') playerPosition.x -= step;
    if (direction === 'right') playerPosition.x += step;
    playerPosition.x = clamp(playerPosition.x, 0.05, .95);
    playerPosition.y = clamp(playerPosition.y, 0.08, .92);
    drawMap();

    const hit = currentEncounterList().find((enemy) => {
        if (enemy.defeated) return false;
        const dx = playerPosition.x - enemy.x;
        const dy = playerPosition.y - enemy.y;
        return Math.sqrt((dx * dx) + (dy * dy)) < .075;
    });
    if (hit) postAction('encounter', {id: hit.id});
}

function renderProgress(stage) {
    elements.stageTrack.querySelectorAll('li').forEach((item) => {
        const itemStage = Number(item.dataset.stage);
        item.classList.toggle('active', itemStage === stage);
        item.classList.toggle('done', itemStage < stage);
        item.classList.toggle('locked', itemStage > stage);
    });
}

function renderLog(log) {
    elements.battleLog.replaceChildren();
    const entries = Array.isArray(log) && log.length ? log.slice().reverse() : ['Đấu trường đang chờ hành động.'];
    entries.forEach((entry, index) => {
        const item = document.createElement('li');
        item.textContent = entry;
        if (index === 0) item.classList.add('latest');
        elements.battleLog.append(item);
    });
}

function renderInstructions(stage) {
    elements.actionArea.replaceChildren();
    const p = document.createElement('p');
    p.className = 'instruction';
    if (stage === 1) {
        p.textContent = 'Dùng ↑ ↓ ← → trên bàn phím. Đi tới biểu tượng ⚡ để chạm trán Wild Pikachu.';
        elements.actionArea.append(p);
    } else if (stage === 2) {
        p.textContent = 'Dùng phím mũi tên để khám phá Gym. Quan sát type, damage và debuff trước khi chọn đối thủ.';
        const wheel = document.createElement('div');
        wheel.className = 'type-wheel';
        wheel.innerHTML = '<span><b>🔥 Fire</b> thắng 🌿 Grass</span><span><b>💧 Water</b> thắng 🔥 Fire</span><span><b>🌿 Grass</b> thắng 💧 Water</span>';
        elements.actionArea.append(p, wheel);
    } else {
        p.textContent = 'Lợi dụng lỗ hổng và chiến thắng trò chơi.';
        elements.actionArea.append(p);
    }
}

function renderBattle() {
    const enemy = gameState.current_enemy;
    const trainer = gameState.trainer;
    if (!enemy) {
        elements.battleView.hidden = true;
        return;
    }
    elements.mapView.hidden = true;
    elements.defeatView.hidden = true;
    elements.clearView.hidden = true;
    elements.battleView.hidden = false;
    elements.battlePlayerSprite.src = `/assets/materials/pokemons/${trainer.starter}.png`;
    elements.battlePlayerName.textContent = trainer.name.toUpperCase();
    elements.battlePlayerType.textContent = trainer.type.toUpperCase();
    elements.battlePlayerDamage.textContent = formatNumber(trainer.damage);
    elements.battleEnemySprite.src = assetPath(enemy);
    elements.battleEnemyName.textContent = enemy.name.toUpperCase();
    elements.battleEnemyType.textContent = enemy.type.toUpperCase();
    elements.battleEnemyDamage.textContent = formatNumber(enemy.damage);
    const debuff = Number(enemy.debuff || 0);
    elements.battleEnemyDebuffRow.hidden = debuff === 0;
    elements.battleEnemyDebuff.textContent = debuff ? `-${formatNumber(debuff)} DMG` : '—';
    elements.battleResult.textContent = enemy.boss
        ? ''
        : (debuff ? `Đối thủ gây debuff -${debuff} damage trước khi so đòn.` : 'Ai có damage lớn hơn sẽ hạ đối thủ bằng một đòn duy nhất.');
    elements.runButton.textContent = '↩ CHẠY';
    focusBattleAction(battleFocusIndex);
}

function renderDefeat() {
    elements.mapView.hidden = true;
    elements.battleView.hidden = true;
    elements.clearView.hidden = true;
    elements.defeatView.hidden = false;
    elements.defeatMessage.textContent = gameState.defeat_message || 'Bạn phải bắt đầu lại từ Màn 1.';
    focusButton(elements.retryButton);
}

function renderClear() {
    const stage = Number(gameState.stage);
    const pending = Number(gameState.pending_stage);
    elements.mapView.hidden = true;
    elements.battleView.hidden = true;
    elements.defeatView.hidden = true;
    elements.clearView.hidden = false;
    elements.flagBox.hidden = stage !== 4;
    elements.continueButton.hidden = stage === 4;
    elements.clearKicker.textContent = stage === 4 ? 'ARENA COMPLETE' : `LEVEL ${stage} CLEAR`;
    elements.clearTitle.textContent = stage === 4 ? 'Bạn đã hoàn thành cả 3 màn!' : 'Chúc mừng!';
    elements.clearMessage.textContent = gameState.clear_message || `Bạn đã vượt qua Màn ${stage}.`;
    elements.continueButton.textContent = pending ? `SANG MÀN ${pending} →` : 'TIẾP TỤC →';
    if (stage === 4) elements.flagValue.textContent = gameState.victory_code || 'CLB_ATTT_DEMO_WINNER';
    if (stage !== 4) focusButton(elements.continueButton);
}

function render() {
    const started = Boolean(gameState && gameState.started);
    elements.startOverlay.hidden = started;
    elements.saveButton.disabled = !started;
    elements.loadButton.disabled = !started;
    document.body.classList.toggle('game-started', started);
    if (!started) {
        renderProgress(0);
        return;
    }

    const stage = Number(gameState.stage);
    const copy = stageCopy[stage] || stageCopy[4];
    const trainer = gameState.trainer;
    elements.worldShell.className = `world-shell stage-${stage}`;
    elements.levelBadge.textContent = copy.badge;
    elements.levelTitle.textContent = copy.title;
    elements.levelStory.textContent = copy.story;
    elements.missionText.textContent = copy.mission;
    elements.sideTrainerName.textContent = trainer.name;
    elements.sideStarter.textContent = `${trainer.starter.toUpperCase()} · ${trainer.type.toUpperCase()}`;
    elements.sideDamage.textContent = formatNumber(trainer.damage);
    renderProgress(stage);

    const levelState = stage === 1 ? gameState.level1 : (stage === 2 ? gameState.level2 : gameState.level3);
    if (stage === 1) {
        elements.progressText.textContent = `WINS: ${levelState.wins} / ${levelState.required_wins}`;
    } else if (stage === 2) {
        elements.progressText.textContent = `GYM CLEAR: ${levelState.advantage_wins || 0} / ${levelState.required_wins}`;
    } else if (stage === 3) {
        elements.progressText.textContent = 'MỤC TIÊU: NULLBYTE Ω';
    } else {
        elements.progressText.textContent = 'FLAG UNLOCKED';
    }

    if (stage <= 3 && mapStage !== stage) setupMap(stage);
    if (stage <= 3 && !gameState.current_enemy && !gameState.pending_stage && !gameState.defeat_message) {
        elements.mapView.hidden = false;
        elements.battleView.hidden = true;
        elements.defeatView.hidden = true;
        elements.clearView.hidden = true;
        renderInstructions(stage);
        elements.mapPrompt.textContent = stage === 1
            ? 'Bấm phím mũi tên để đi tìm ⚡ Pikachu.'
            : (stage === 2 ? 'Khám phá Hệ và cân nhắc từng đối thủ trước khi vào battle.' : 'Đi tới ☠ NullByte Ω ở và chinh phục nó.');
        drawMap();
    } else if (gameState.defeat_message) {
        renderDefeat();
    } else if (gameState.pending_stage || stage === 4) {
        renderClear();
    } else {
        renderBattle();
    }

    renderLog(gameState.log);
}

async function loadSave(file) {
    if (!file || busy || !gameState?.started) return;
    setBusy(true);
    const form = new FormData();
    form.append('save', file);
    try {
        const response = await fetch(`${SAVE_URL}?action=load`, {method: 'POST', body: form});
        const payload = await response.json();
        if (payload.state) {
            gameState = payload.state;
            render();
        }
        showToast(payload.message, !payload.ok);
    } catch (error) {
        showToast('Không thể nạp file save.', true);
    } finally {
        elements.saveInput.value = '';
        setBusy(false);
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(focusVisibleAction);
        } else {
            window.setTimeout(focusVisibleAction, 0);
        }
    }
}

document.querySelectorAll('.starter-option').forEach((button) => {
    button.addEventListener('click', () => {
        selectedStarter = button.dataset.starter;
        document.querySelectorAll('.starter-option').forEach((item) => item.classList.toggle('selected', item === button));
    });
});

elements.startForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    await postAction('start', {name: elements.nameInput.value, starter: selectedStarter});
});

elements.canvas.addEventListener('click', () => elements.canvas.focus({preventScroll: true}));
document.addEventListener('keydown', (event) => {
    const keys = {ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right'};
    const activate = event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar' || event.code === 'Space';
    if (!gameState?.started || !elements.startOverlay.hidden) return;
    if (busy) return;

    if (gameState.defeat_message) {
        if (activate) {
            event.preventDefault();
            elements.retryButton.click();
        }
        return;
    }

    if (gameState.pending_stage) {
        if (activate && !elements.continueButton.hidden) {
            event.preventDefault();
            elements.continueButton.click();
        }
        return;
    }

    if (gameState.current_enemy) {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            event.preventDefault();
            focusBattleAction(battleFocusIndex - 1);
        } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            event.preventDefault();
            focusBattleAction(battleFocusIndex + 1);
        } else if (activate) {
            event.preventDefault();
            [elements.fightButton, elements.runButton][battleFocusIndex].click();
        }
        return;
    }

    if (keys[event.key]) {
        event.preventDefault();
        movePlayer(keys[event.key]);
    }
});

elements.fightButton.addEventListener('focus', () => {
    battleFocusIndex = 0;
});
elements.runButton.addEventListener('focus', () => {
    battleFocusIndex = 1;
});
elements.fightButton.addEventListener('click', async () => {
    elements.battleResult.textContent = 'Đang tính toán damage...';
    await postAction('battle_resolve');
});
elements.runButton.addEventListener('click', () => postAction('battle_run'));
elements.retryButton.addEventListener('click', () => postAction('dismiss_defeat'));
elements.continueButton.addEventListener('click', () => postAction('advance_stage'));
for (const button of [elements.retryButton, elements.continueButton]) {
    button.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ' || event.code === 'Space') {
            event.preventDefault();
            button.click();
        }
    });
}

elements.saveButton.addEventListener('click', () => {
    if (!gameState?.started) return showToast('Hãy bắt đầu game trước.', true);
    window.location.assign(`${SAVE_URL}?action=save`);
});
elements.loadButton.addEventListener('click', () => {
    if (!gameState?.started) return showToast('Hãy bắt đầu game trước.', true);
    elements.saveInput.click();
});
elements.saveInput.addEventListener('change', () => loadSave(elements.saveInput.files[0]));

elements.resetButton.addEventListener('click', async () => {
    if (!gameState || !gameState.started || window.confirm('Xóa tiến trình hiện tại và chơi lại từ đầu?')) {
        await postAction('reset');
    }
});

elements.musicButton.addEventListener('click', async () => {
    if (elements.music.paused) {
        try {
            elements.music.volume = 0.28;
            await elements.music.play();
            elements.musicButton.textContent = '♫ Nhạc: ON';
            elements.musicButton.setAttribute('aria-pressed', 'true');
        } catch (error) {
            showToast('Trình duyệt đang chặn phát nhạc.', true);
        }
    } else {
        elements.music.pause();
        elements.musicButton.textContent = '♫ Nhạc: OFF';
        elements.musicButton.setAttribute('aria-pressed', 'false');
    }
});

window.addEventListener('resize', drawMap);
if (document.fonts?.ready) document.fonts.ready.then(drawMap);
postAction('status');
