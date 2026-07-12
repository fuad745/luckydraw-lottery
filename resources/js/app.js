import confetti from 'canvas-confetti';

/*
|--------------------------------------------------------------------------
| Telegram Mini App bridge
|--------------------------------------------------------------------------
| Wires the Telegram WebApp SDK (loaded from telegram.org in the layout)
| into Livewire: authenticates every request with signed initData, syncs
| the native theme, and exposes haptics / confetti / share helpers.
*/

const tg = window.Telegram?.WebApp;

function applyTelegramTheme() {
    if (!tg) return;
    const p = tg.themeParams || {};
    const root = document.documentElement;
    const set = (name, value) => value && root.style.setProperty(name, value);
    set('--tg-bg', p.bg_color);
    set('--tg-text', p.text_color);
    set('--tg-hint', p.hint_color);
    set('--tg-link', p.link_color);
    set('--tg-button', p.button_color);
    set('--tg-button-text', p.button_text_color);
    root.classList.toggle('tg-light', tg.colorScheme === 'light');
}

if (tg) {
    tg.ready();
    tg.expand();
    try { tg.disableVerticalSwipes?.(); } catch (e) { /* older clients */ }
    applyTelegramTheme();
    tg.onEvent('themeChanged', applyTelegramTheme);
}

// Haptic feedback helper (no-op outside Telegram).
window.luckyHaptic = (type = 'impact', style = 'medium') => {
    try {
        const h = tg?.HapticFeedback;
        if (!h) return;
        if (type === 'notification') h.notificationOccurred(style);
        else if (type === 'selection') h.selectionChanged();
        else h.impactOccurred(style);
    } catch (e) { /* ignore */ }
};

// Celebration burst — fired on a winning draw.
window.luckyConfetti = () => {
    const colors = ['#FFD700', '#FDB931', '#FFF1A8', '#ffffff'];
    const end = Date.now() + 2500;
    (function frame() {
        confetti({ particleCount: 5, angle: 60, spread: 70, origin: { x: 0 }, colors });
        confetti({ particleCount: 5, angle: 120, spread: 70, origin: { x: 1 }, colors });
        if (Date.now() < end) requestAnimationFrame(frame);
    })();
    confetti({ particleCount: 160, spread: 100, origin: { y: 0.6 }, colors });
    window.luckyHaptic('notification', 'success');
};

// Request the user's verified phone number via Telegram's contact prompt.
window.luckyRequestContact = (onPhone) => {
    if (!tg?.requestContact) {
        onPhone(null);
        return;
    }
    const handler = (e) => {
        const phone = e?.responseUnsafe?.contact?.phone_number;
        if (phone) {
            try { tg.offEvent('contactRequested', handler); } catch (err) { /* noop */ }
            onPhone(phone);
        }
    };
    try { tg.onEvent('contactRequested', handler); } catch (err) { /* noop */ }
    tg.requestContact((ok, res) => {
        const phone = res?.responseUnsafe?.contact?.phone_number || res?.contact?.phone_number;
        if (phone) {
            onPhone(phone);
        } else if (!ok) {
            // Declined/dismissed — unblock the caller so the button can retry.
            try { tg.offEvent('contactRequested', handler); } catch (err) { /* noop */ }
            onPhone(null);
        }
    });
};

/*
| Lightweight Web Audio sound effects (no asset files). Safe no-ops if the
| browser blocks audio until a user gesture (the app open counts as one).
*/
let _audioCtx = null;
function audioCtx() {
    try {
        _audioCtx = _audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (_audioCtx.state === 'suspended') _audioCtx.resume();
        return _audioCtx;
    } catch (e) { return null; }
}
function beep(freq, duration = 0.06, type = 'square', gain = 0.04) {
    if (localStorage.getItem('lucky_sound') === 'off') return; // respect Settings toggle
    const ctx = audioCtx();
    if (!ctx) return;
    const osc = ctx.createOscillator();
    const g = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    g.gain.value = gain;
    osc.connect(g);
    g.connect(ctx.destination);
    const now = ctx.currentTime;
    g.gain.setValueAtTime(gain, now);
    g.gain.exponentialRampToValueAtTime(0.0001, now + duration);
    osc.start(now);
    osc.stop(now + duration);
}
window.luckyTick = () => beep(220 + Math.random() * 120, 0.03, 'square', 0.025);
window.luckyFanfare = () => {
    [523, 659, 784, 1047].forEach((f, i) => setTimeout(() => beep(f, 0.18, 'triangle', 0.06), i * 130));
};

// Native Telegram share dialog.
window.luckyShare = (text, url) => {
    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
    if (tg?.openTelegramLink) tg.openTelegramLink(shareUrl);
    else window.open(shareUrl, '_blank');
};

/*
| Livewire integration: attach signed initData to every request and react
| to server-dispatched browser events.
*/
// Cinematic winner reveal: pull each winning ball one-by-one with a
// number-scramble that locks onto the real number.
document.addEventListener('alpine:init', () => {
    // Animated number count-up (respects reduced-motion).
    window.Alpine.data('counter', (target, decimals = 2) => ({
        value: target,
        init() {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const end = Number(target) || 0;
            if (reduce || end === 0) { this.value = end; return; }
            const start = performance.now();
            const dur = 800;
            const tick = (now) => {
                const p = Math.min(1, (now - start) / dur);
                this.value = end * (1 - Math.pow(1 - p, 3)); // ease-out cubic
                if (p < 1) requestAnimationFrame(tick); else this.value = end;
            };
            this.value = 0;
            requestAnimationFrame(tick);
        },
        get display() { return this.value.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals }); },
    }));

    window.Alpine.data('reveal', (cfg) => ({
        stage: 'rolling',
        shown: 0,
        winners: cfg.winners || [],
        display: (cfg.winners || []).map(() => '?'),
        locked: (cfg.winners || []).map(() => false),
        total: cfg.total || 50,
        key: '__revealed_' + cfg.roundId,
        init() {
            // Already played this round (e.g. revisiting) → show the final state.
            if (window[this.key]) {
                this.shown = this.winners.length;
                this.display = this.winners.map((w) => w.n);
                this.locked = this.winners.map(() => true);
                this.stage = 'done';
                return;
            }
            window[this.key] = true;
            this.$nextTick(() => this.run());
        },
        async run() {
            await this.sleep(1200); // drum tumbles
            for (let i = 0; i < this.winners.length; i++) {
                await this.scramble(i);
                window.luckyHaptic && window.luckyHaptic('impact', 'heavy');
                await this.sleep(550);
            }
            this.stage = 'done';
            window.luckyConfetti && window.luckyConfetti();
            window.luckyFanfare && window.luckyFanfare();
        },
        scramble(i) {
            return new Promise((resolve) => {
                this.shown = Math.max(this.shown, i + 1);
                let ticks = 0;
                const iv = setInterval(() => {
                    this.display[i] = 1 + Math.floor(Math.random() * this.total);
                    window.luckyTick && window.luckyTick();
                    if (++ticks >= 14) {
                        clearInterval(iv);
                        this.display[i] = this.winners[i].n;
                        this.locked[i] = true;
                        resolve();
                    }
                }, 65);
            });
        },
        sleep(ms) { return new Promise((r) => setTimeout(r, ms)); },
    }));
});

document.addEventListener('livewire:init', () => {
    const initData = tg?.initData || '';

    window.Livewire.hook('request', ({ options }) => {
        if (initData) {
            options.headers = options.headers || {};
            options.headers['X-Telegram-Init-Data'] = initData;
        }
    });

    // Server -> browser events.
    window.Livewire.on('confetti', () => window.luckyConfetti());
    window.Livewire.on('haptic', (e) => {
        const p = Array.isArray(e) ? e[0] : e;
        window.luckyHaptic(p?.type, p?.style);
    });
    window.Livewire.on('share', (e) => {
        const p = Array.isArray(e) ? e[0] : e;
        window.luckyShare(p?.text || '', p?.url || '');
    });

    // First paint authenticated: re-render components once initData is available.
    if (initData) {
        requestAnimationFrame(() => window.Livewire.all().forEach((c) => c.$refresh()));
    }
});

// Telegram BackButton mirrors browser history within the Mini App.
if (tg?.BackButton) {
    const updateBackButton = () => {
        if (window.location.pathname !== '/') tg.BackButton.show();
        else tg.BackButton.hide();
    };
    tg.BackButton.onClick(() => window.history.back());
    document.addEventListener('livewire:navigated', updateBackButton);
    updateBackButton();
}
