<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocket Money</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --surface: rgba(255, 255, 255, 0.82);
            --surface-strong: #ffffff;
            --text: #132238;
            --muted: #62748a;
            --line: rgba(19, 34, 56, 0.1);
            --primary: #0f766e;
            --primary-soft: rgba(15, 118, 110, 0.12);
            --danger: #dc2626;
            --success: #15803d;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            --hero: radial-gradient(circle at top left, rgba(14, 165, 233, 0.18), transparent 38%),
                radial-gradient(circle at top right, rgba(15, 118, 110, 0.16), transparent 32%),
                linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
        }

        :root[data-theme="dark"] {
            color-scheme: dark;
            --bg: #08111f;
            --surface: rgba(8, 17, 31, 0.76);
            --surface-strong: #0f1b31;
            --text: #e5eef9;
            --muted: #95a8c2;
            --line: rgba(148, 163, 184, 0.18);
            --primary: #34d399;
            --primary-soft: rgba(52, 211, 153, 0.16);
            --danger: #f87171;
            --success: #4ade80;
            --shadow: 0 28px 70px rgba(2, 6, 23, 0.5);
            --hero: radial-gradient(circle at top left, rgba(56, 189, 248, 0.12), transparent 38%),
                radial-gradient(circle at top right, rgba(52, 211, 153, 0.1), transparent 32%),
                linear-gradient(180deg, #07101d 0%, #0b1628 100%);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            background: var(--hero);
            color: var(--text);
            transition: background 180ms ease, color 180ms ease;
        }

        .page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
        }

        .brand h1 {
            margin: 0;
            font-size: clamp(1.8rem, 4vw, 3rem);
            letter-spacing: -0.04em;
        }

        .brand p {
            margin: 8px 0 0;
            color: var(--muted);
            max-width: 560px;
            line-height: 1.5;
        }

        .theme-switcher {
            display: inline-flex;
            gap: 8px;
            padding: 8px;
            border-radius: 999px;
            background: var(--surface);
            border: 1px solid var(--line);
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow);
        }

        .theme-button {
            border: 0;
            background: transparent;
            color: var(--muted);
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 160ms ease, color 160ms ease, transform 160ms ease;
        }

        .theme-button:hover,
        .theme-button[aria-pressed="true"] {
            background: var(--primary-soft);
            color: var(--text);
            transform: translateY(-1px);
        }

        .hero-card {
            display: grid;
            grid-template-columns: 1.3fr 0.9fr;
            gap: 22px;
            margin-bottom: 24px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 24px;
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow);
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 14px;
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .headline {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3.3rem);
            line-height: 1;
            letter-spacing: -0.05em;
        }

        .subcopy {
            margin: 16px 0 0;
            max-width: 54ch;
            line-height: 1.65;
            color: var(--muted);
        }

        .balance {
            display: grid;
            gap: 16px;
            align-content: space-between;
        }

        .balance-label {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .balance-amount {
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 800;
            letter-spacing: -0.06em;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card,
        .expense-item {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 18px;
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow);
        }

        .stat-card span,
        .expense-item span {
            display: block;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .stat-card strong,
        .expense-item strong {
            display: block;
            margin-top: 10px;
            font-size: 1.7rem;
            letter-spacing: -0.04em;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .action {
            text-decoration: none;
            color: var(--text);
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--shadow);
            transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
        }

        .action:hover {
            transform: translateY(-2px);
            border-color: rgba(15, 118, 110, 0.4);
            background: var(--surface-strong);
        }

        .action-title {
            font-weight: 700;
            margin-bottom: 8px;
        }

        .action-copy {
            color: var(--muted);
            line-height: 1.5;
        }

        .expense-list {
            display: grid;
            gap: 14px;
        }

        .expense-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .expense-row p {
            margin: 0;
        }

        .expense-row small {
            color: var(--muted);
        }

        .debit {
            color: var(--danger);
            font-weight: 700;
        }

        .credit {
            color: var(--success);
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .hero-card,
            .stat-grid,
            .actions {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <div class="brand">
                <h1>Pocket Money</h1>
                <p>Track every rupee with a calmer dashboard, a cleaner visual rhythm, and a theme that fits the user's day or night.</p>
            </div>

            <div class="theme-switcher" role="group" aria-label="Theme switcher">
                <button class="theme-button" data-theme-option="light" type="button">Light</button>
                <button class="theme-button" data-theme-option="dark" type="button">Dark</button>
                <button class="theme-button" data-theme-option="system" type="button">System</button>
            </div>
        </header>

        <section class="hero-card">
            <div class="panel">
                <span class="eyebrow">Appearance Ready</span>
                <h2 class="headline">Professional light and dark mode foundation.</h2>
                <p class="subcopy">The UI now supports light, dark, and system themes with consistent cards, readable contrast, and a single visual language across summary, actions, and activity.</p>
            </div>

            <div class="panel balance">
                <div>
                    <div class="balance-label">Current Balance</div>
                    <div class="balance-amount">Rs 5,000</div>
                </div>
                <div class="balance-label">Theme selection is remembered locally in this demo page and can be saved through the preferences API for the app.</div>
            </div>
        </section>

        <section class="stat-grid">
            <article class="stat-card">
                <span>Credits</span>
                <strong class="credit">Rs 10,000</strong>
            </article>
            <article class="stat-card">
                <span>Debits</span>
                <strong class="debit">Rs 5,000</strong>
            </article>
            <article class="stat-card">
                <span>Expense Count</span>
                <strong>24</strong>
            </article>
        </section>

        <section class="actions">
            <a href="#" class="action">
                <div class="action-title">View Expenses</div>
                <div class="action-copy">Browse single, multi, scan, SMS, and voice entries in one place.</div>
            </a>
            <a href="#" class="action">
                <div class="action-title">Add Expense</div>
                <div class="action-copy">Quickly record spending with clean category and wallet selection.</div>
            </a>
            <a href="#" class="action">
                <div class="action-title">Split Expense</div>
                <div class="action-copy">Capture shared costs while keeping the home page totals clear.</div>
            </a>
            <a href="#" class="action">
                <div class="action-title">Scan Receipt</div>
                <div class="action-copy">Move from paper to tracked expense without breaking the theme system.</div>
            </a>
        </section>

        <section class="expense-list">
            <article class="expense-item">
                <div class="expense-row">
                    <div>
                        <p><strong>Groceries</strong></p>
                        <small>Single expense • Food & Dining</small>
                    </div>
                    <div class="debit">-Rs 1,200</div>
                </div>
            </article>
            <article class="expense-item">
                <div class="expense-row">
                    <div>
                        <p><strong>Electricity Bill</strong></p>
                        <small>SMS confirmed • Bills & Utilities</small>
                    </div>
                    <div class="debit">-Rs 800</div>
                </div>
            </article>
        </section>
    </div>

    <script>
        (function () {
            const storageKey = 'pocket-money-theme-mode';
            const root = document.documentElement;
            const buttons = document.querySelectorAll('[data-theme-option]');
            const systemQuery = window.matchMedia('(prefers-color-scheme: dark)');

            function getStoredTheme() {
                return localStorage.getItem(storageKey) || 'system';
            }

            function resolvedTheme(mode) {
                if (mode === 'system') {
                    return systemQuery.matches ? 'dark' : 'light';
                }

                return mode;
            }

            function applyTheme(mode) {
                root.dataset.theme = resolvedTheme(mode);

                buttons.forEach((button) => {
                    button.setAttribute('aria-pressed', button.dataset.themeOption === mode ? 'true' : 'false');
                });
            }

            buttons.forEach((button) => {
                button.addEventListener('click', function () {
                    const mode = this.dataset.themeOption;
                    localStorage.setItem(storageKey, mode);
                    applyTheme(mode);
                });
            });

            if (typeof systemQuery.addEventListener === 'function') {
                systemQuery.addEventListener('change', function () {
                    if (getStoredTheme() === 'system') {
                        applyTheme('system');
                    }
                });
            }

            applyTheme(getStoredTheme());
        })();
    </script>
</body>
</html>
