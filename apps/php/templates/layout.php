<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title ?? 'Cataloga') ?></title>
  <style>
    :root {
      --font-sans: "Noto Sans JP", "Segoe UI", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
      --font-mono: "JetBrains Mono", "SFMono-Regular", Menlo, Monaco, Consolas, monospace;
      --bg: #eef4f9;
      --panel: #ffffff;
      --panel-soft: #f3f8fd;
      --line: #e2e8f0;
      --line-strong: #cbd5e1;
      --text: #0f172a;
      --muted: #475569;
      --sky: #0369a1;
      --sky-soft: #e0f2fe;
      --teal: #0f766e;
      --rose: #be123c;
      --amber: #b45309;
      --radius-md: 10px;
      --radius-lg: 14px;
      --shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
      --shadow-lg: 0 24px 80px rgba(15, 23, 42, 0.08);
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      min-height: 100%;
      background: radial-gradient(circle at 15% -10%, #dbeafe 0%, rgba(219, 234, 254, 0.35) 22%, transparent 58%), var(--bg);
      color: var(--text);
      font-family: var(--font-sans);
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    code, pre {
      font-family: var(--font-mono);
    }

    .site-header {
      position: sticky;
      top: 0;
      z-index: 20;
      border-bottom: 1px solid var(--line);
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(8px);
    }

    .header-inner {
      max-width: 1760px;
      margin: 0 auto;
      padding: 0.8rem 1rem;
      display: grid;
      gap: 0.68rem;
    }

    .brand-row {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 0.8rem;
      flex-wrap: wrap;
    }

    .brand-eyebrow {
      margin: 0;
      font-size: 11px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      font-weight: 600;
      color: #64748b;
    }

    .brand-title {
      margin: 0.2rem 0 0;
      font-size: clamp(1.1rem, 2vw, 1.35rem);
      line-height: 1.25;
      font-weight: 700;
      letter-spacing: -0.015em;
      color: #0b1325;
    }

    .brand-meta {
      margin: 0;
      color: var(--muted);
      font-size: 0.86rem;
      white-space: nowrap;
    }

    nav {
      display: flex;
      gap: 0.35rem;
      overflow-x: auto;
      padding-bottom: 0.1rem;
    }

    nav a {
      border-radius: 8px;
      border: 1px solid transparent;
      color: #334155;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      white-space: nowrap;
      padding: 0.45rem 0.62rem;
      transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
    }

    nav a:hover {
      color: var(--sky);
      background: #f8fafc;
      border-color: var(--line);
    }

    nav a.active {
      color: var(--sky);
      background: #fff;
      border-color: #bae6fd;
      box-shadow: 0 1px 2px rgba(14, 165, 233, 0.12);
    }

    .workspace {
      max-width: 1760px;
      margin: 0 auto;
      padding: 1rem;
      display: grid;
      gap: 0.92rem;
    }

    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 1rem;
    }

    .panel.soft {
      background: var(--panel-soft);
    }

    .eyebrow {
      margin: 0;
      color: #64748b;
      font-size: 11px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      font-weight: 700;
    }

    h1, h2, h3 {
      margin: 0;
      line-height: 1.3;
      letter-spacing: -0.02em;
    }

    h1 { font-size: clamp(1.4rem, 3vw, 2rem); }
    h2 { font-size: clamp(1.05rem, 2vw, 1.35rem); }
    h3 { font-size: 0.95rem; }

    .meta {
      color: var(--muted);
      font-size: 0.88rem;
      line-height: 1.65;
      margin: 0;
    }

    .title-row {
      display: flex;
      justify-content: space-between;
      align-items: start;
      gap: 0.8rem;
      flex-wrap: wrap;
      margin-bottom: 0.85rem;
    }

    .title-stack {
      display: grid;
      gap: 0.35rem;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
      align-items: center;
      margin-top: 0.95rem;
    }

    .primary-button,
    .secondary-button,
    .button-link,
    button {
      border-radius: 9px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 0.82rem;
      font-size: 0.83rem;
      line-height: 1.2;
      font-weight: 700;
      border: 1px solid transparent;
      cursor: pointer;
      transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .primary-button,
    .button-link,
    button {
      background: #0f172a;
      color: #fff;
      text-decoration: none;
    }

    .primary-button:hover,
    .button-link:hover,
    button:hover {
      background: #0369a1;
    }

    .secondary-button,
    .button-link.secondary,
    button.secondary {
      background: #fff;
      color: #334155;
      border-color: var(--line);
    }

    .secondary-button:hover,
    .button-link.secondary:hover,
    button.secondary:hover {
      color: var(--sky);
      border-color: #bae6fd;
      background: #f8fafc;
    }

    .danger-button,
    button.danger {
      background: #fff1f2;
      color: var(--rose);
      border-color: #fecdd3;
    }

    .danger-button:hover,
    button.danger:hover {
      background: #ffe4e6;
    }

    .button-link {
      text-decoration: none;
    }

    .button-link.text,
    .text-link {
      background: transparent;
      color: var(--sky);
      border: 0;
      padding: 0;
      font-weight: 700;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    .button-link.text:hover,
    .text-link:hover {
      color: #075985;
      background: transparent;
    }

    .metrics {
      display: grid;
      gap: 0.75rem;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    }

    .metric-card {
      border: 1px solid var(--line);
      border-radius: 11px;
      background: #fff;
      padding: 0.65rem 0.8rem;
    }

    .metric-card span {
      display: block;
      color: #64748b;
      font-size: 10px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      font-weight: 700;
    }

    .metric-card strong {
      display: block;
      margin-top: 0.32rem;
      font-size: clamp(1.15rem, 2vw, 1.5rem);
      line-height: 1.2;
    }

    .metric-card p {
      margin: 0.32rem 0 0;
      color: var(--muted);
      font-size: 0.78rem;
    }

    .table-shell {
      overflow-x: auto;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: #fff;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      min-width: 720px;
    }

    th,
    td {
      padding: 0.66rem 0.72rem;
      text-align: left;
      vertical-align: top;
      border-bottom: 1px solid var(--line);
      font-size: 0.86rem;
    }

    th {
      background: #f8fafc;
      color: #334155;
      font-size: 0.76rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      font-weight: 700;
      white-space: nowrap;
      position: sticky;
      top: 0;
    }

    tbody tr:hover td {
      background: #f8fafc;
    }

    tbody tr:last-child td {
      border-bottom: 0;
    }

    .mono {
      font-family: var(--font-mono);
      font-size: 0.8rem;
      word-break: break-all;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.2rem 0.55rem;
      font-size: 0.74rem;
      border: 1px solid var(--line);
      background: #fff;
      color: #334155;
      font-weight: 600;
    }

    .pill.ok { background: #f0fdfa; border-color: #99f6e4; color: var(--teal); }
    .pill.warn { background: #fffbeb; border-color: #fde68a; color: var(--amber); }
    .pill.error { background: #fff1f2; border-color: #fecdd3; color: var(--rose); }

    .split {
      display: grid;
      gap: 0.9rem;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }

    pre {
      margin: 0;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: #f8fafc;
      color: #1e293b;
      padding: 0.78rem;
      overflow-x: auto;
      font-size: 0.81rem;
      line-height: 1.6;
    }

    input[type="text"],
    textarea,
    select {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 9px;
      background: #fff;
      color: var(--text);
      padding: 0.56rem 0.62rem;
      font-size: 0.9rem;
    }

    textarea {
      min-height: 96px;
      resize: vertical;
    }

    label {
      display: block;
      margin: 0;
      font-size: 0.82rem;
      color: #334155;
      font-weight: 700;
      letter-spacing: 0.01em;
    }

    .form-stack {
      display: grid;
      gap: 0.72rem;
    }

    .field {
      display: grid;
      gap: 0.35rem;
    }

    .field.inline {
      grid-template-columns: auto 1fr;
      align-items: center;
      gap: 0.55rem;
    }

    .field.inline label {
      margin: 0;
    }

    .checkbox {
      width: auto;
      margin: 0;
    }

    ul.clean {
      margin: 0;
      padding-left: 1.05rem;
      display: grid;
      gap: 0.35rem;
      font-size: 0.9rem;
    }

    .ok { color: var(--teal); }
    .error { color: var(--rose); }

    .empty-state {
      border: 1px dashed var(--line-strong);
      border-radius: 11px;
      background: #f8fafc;
      color: var(--muted);
      padding: 1rem;
      font-size: 0.88rem;
    }

    .graph-layout {
      display: grid;
      gap: 0.9rem;
      grid-template-columns: minmax(0, 1.7fr) minmax(320px, 1fr);
      align-items: start;
    }

    .graph-panel {
      min-height: 620px;
    }

    .graph-toolbar {
      display: grid;
      gap: 0.55rem;
      grid-template-columns: auto minmax(180px, 1fr) auto auto;
      align-items: center;
      margin-bottom: 0.6rem;
    }

    .graph-toolbar-label {
      margin: 0;
      font-size: 0.82rem;
      color: #334155;
      font-weight: 700;
    }

    .graph-canvas-shell {
      margin-top: 0.65rem;
      height: 520px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
      overflow: hidden;
    }

    #graph-canvas {
      width: 100%;
      height: 100%;
      display: block;
      cursor: grab;
    }

    #graph-canvas:active {
      cursor: grabbing;
    }

    .graph-side {
      min-height: 620px;
      display: grid;
      align-content: start;
      gap: 0.72rem;
    }

    .graph-detail {
      border: 1px solid var(--line);
      border-radius: 11px;
      background: #f8fafc;
      padding: 0.75rem;
    }

    .graph-detail-grid {
      display: grid;
      gap: 0.62rem;
    }

    .graph-detail-grid p {
      margin: 0.2rem 0 0;
      font-size: 0.88rem;
      color: #0f172a;
      line-height: 1.5;
    }

    .chip-row {
      display: flex;
      gap: 0.42rem;
      flex-wrap: wrap;
      margin-top: 0.35rem;
    }

    .chip {
      border: 1px solid #bfdbfe;
      background: #eff6ff;
      color: #1e3a8a;
      border-radius: 999px;
      padding: 0.2rem 0.6rem;
      font-size: 0.74rem;
      line-height: 1.25;
      cursor: pointer;
    }

    .chip:hover {
      background: #dbeafe;
    }

    .graph-type-list {
      display: grid;
      gap: 0.45rem;
    }

    .graph-type-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      padding: 0.45rem 0.6rem;
      font-size: 0.82rem;
      color: #334155;
    }

    .graph-type-item strong {
      color: #0f172a;
      font-size: 0.86rem;
    }

    .mt-2 { margin-top: 0.8rem; }
    .mt-3 { margin-top: 0.9rem; }

    :focus-visible {
      outline: 3px solid #bae6fd;
      outline-offset: 2px;
    }

    @media (max-width: 760px) {
      .workspace {
        padding: 0.8rem;
      }

      .header-inner {
        padding: 0.72rem 0.8rem;
      }

      table {
        min-width: 640px;
      }

      th,
      td {
        font-size: 0.8rem;
        padding: 0.55rem 0.58rem;
      }

      nav a {
        font-size: 0.73rem;
      }

      .graph-layout {
        grid-template-columns: 1fr;
      }

      .graph-panel,
      .graph-side {
        min-height: 0;
      }

      .graph-toolbar {
        grid-template-columns: 1fr 1fr;
      }

      .graph-toolbar-label {
        grid-column: 1 / span 2;
      }

      #graph-search {
        grid-column: 1 / span 2;
      }

      .graph-canvas-shell {
        height: 460px;
      }
    }
  </style>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="brand-row">
      <div>
        <p class="brand-eyebrow">Cataloga v2</p>
        <h1 class="brand-title">Registry Workspace</h1>
      </div>
      <p class="brand-meta">Git/file-backed, mutation-first operations</p>
    </div>
    <nav>
      <a href="/" class="<?= ($currentPath ?? '') === '/' ? 'active' : '' ?>">Dashboard</a>
      <a href="/graph" class="<?= ($currentPath ?? '') === '/graph' ? 'active' : '' ?>">Graph</a>
      <a href="/entities" class="<?= str_starts_with((string) ($currentPath ?? ''), '/entities') ? 'active' : '' ?>">Entities</a>
      <a href="/relations" class="<?= str_starts_with((string) ($currentPath ?? ''), '/relations') ? 'active' : '' ?>">Relations</a>
      <a href="/domain-packs" class="<?= str_starts_with((string) ($currentPath ?? ''), '/domain-packs') ? 'active' : '' ?>">Domain Packs</a>
      <a href="/changes" class="<?= str_starts_with((string) ($currentPath ?? ''), '/changes') ? 'active' : '' ?>">Changes</a>
      <a href="/validation" class="<?= ($currentPath ?? '') === '/validation' ? 'active' : '' ?>">Validation</a>
      <a href="/git/diff" class="<?= ($currentPath ?? '') === '/git/diff' ? 'active' : '' ?>">Git Diff</a>
    </nav>
  </div>
</header>

<main class="workspace">
  <?= $content ?>
</main>
</body>
</html>
