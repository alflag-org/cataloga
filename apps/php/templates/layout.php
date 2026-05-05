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
      --bg: #f7f9fc;
      --panel: #ffffff;
      --line: #e2e8f0;
      --text: #0f172a;
      --muted: #475569;
      --primary: #0f172a;
      --primary-soft: #e2e8f0;
      --valid: #15803d;
      --warn: #b45309;
      --error: #b91c1c;
      --info: #1d4ed8;
      --radius: 12px;
      --shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      min-height: 100%;
      background: linear-gradient(180deg, #f8fbff 0%, var(--bg) 42%);
      color: var(--text);
      font-family: var(--font-sans);
    }

    a { color: inherit; text-decoration: none; }
    code, pre, .mono { font-family: var(--font-mono); }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      border-bottom: 1px solid var(--line);
      background: rgba(255, 255, 255, 0.93);
      backdrop-filter: blur(8px);
    }

    .topbar-inner {
      max-width: 1440px;
      margin: 0 auto;
      padding: 0.75rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .brand {
      display: flex;
      align-items: baseline;
      gap: 0.65rem;
    }

    .brand strong {
      font-size: 1.1rem;
      letter-spacing: -0.015em;
    }

    .brand span {
      color: var(--muted);
      font-size: 0.82rem;
      white-space: nowrap;
    }

    .searchbar {
      display: flex;
      gap: 0.5rem;
      flex: 1;
      min-width: 220px;
      max-width: 520px;
    }

    .searchbar input[type="search"] {
      min-width: 0;
    }

    .searchbar button {
      flex: 0 0 auto;
      white-space: nowrap;
      min-width: 4.6rem;
    }

    .top-actions {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .page-shell {
      max-width: 1440px;
      margin: 0 auto;
      padding: 1rem;
      display: grid;
      grid-template-columns: 220px minmax(0, 1fr);
      gap: 1rem;
      align-items: start;
    }

    .sidebar {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 0.6rem;
      position: sticky;
      top: 84px;
    }

    .sidebar nav {
      display: grid;
      gap: 0.25rem;
    }

    .sidebar a {
      border-radius: 10px;
      padding: 0.52rem 0.62rem;
      font-size: 0.88rem;
      color: #334155;
      border: 1px solid transparent;
    }

    .sidebar a:hover {
      background: #f8fafc;
      border-color: var(--line);
    }

    .sidebar a.active {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #0f172a;
      font-weight: 700;
    }

    .workspace {
      display: grid;
      gap: 0.9rem;
      min-width: 0;
    }

    .panel {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 1rem;
      min-width: 0;
    }

    .panel.soft {
      background: #fcfdff;
    }

    .title-row {
      display: flex;
      justify-content: space-between;
      align-items: start;
      gap: 0.8rem;
      flex-wrap: wrap;
      margin-bottom: 0.85rem;
    }

    .title-row > .actions {
      margin-left: auto;
      justify-content: flex-end;
    }

    .title-stack { display: grid; gap: 0.32rem; }

    .eyebrow {
      margin: 0;
      color: #64748b;
      font-size: 11px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      font-weight: 700;
    }

    h1, h2, h3 { margin: 0; line-height: 1.3; }
    h1 { font-size: 1.28rem; }
    h2 { font-size: 1.22rem; }
    h3 { font-size: 1rem; }

    .meta {
      margin: 0;
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.6;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }

    .form-actions {
      justify-content: flex-end;
      padding-top: 0.25rem;
    }

    .primary-button,
    .secondary-button,
    button {
      border-radius: 10px;
      border: 1px solid transparent;
      padding: 0.58rem 0.9rem;
      min-height: 2.4rem;
      font-size: 0.83rem;
      font-weight: 700;
      line-height: 1.2;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .primary-button,
    button,
    .button-link {
      background: var(--primary);
      color: #fff;
    }

    .primary-button:hover,
    button:hover,
    .button-link:hover {
      background: #334155;
    }

    .secondary-button,
    button.secondary {
      background: #fff;
      color: #334155;
      border-color: var(--line);
    }

    .secondary-button:hover,
    button.secondary:hover {
      background: #f8fafc;
      border-color: #cbd5e1;
    }

    .danger-button,
    button.danger {
      background: #fff1f2;
      color: var(--error);
      border-color: #fecdd3;
    }

    .danger-button:hover,
    button.danger:hover {
      background: #ffe4e6;
    }

    .text-link {
      color: #0f172a;
      font-weight: 700;
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    .metrics {
      display: grid;
      gap: 0.75rem;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    }

    .metric-card {
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      padding: 0.7rem 0.8rem;
    }

    .metric-card span {
      display: block;
      color: #64748b;
      font-size: 10px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 700;
    }

    .metric-card strong {
      display: block;
      margin-top: 0.35rem;
      font-size: 1.4rem;
      line-height: 1.2;
    }

    .metric-card p {
      margin: 0.34rem 0 0;
      color: var(--muted);
      font-size: 0.82rem;
    }

    .table-shell {
      overflow-x: auto;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: #fff;
      max-width: 100%;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      min-width: 720px;
    }

    th,
    td {
      padding: 0.62rem 0.7rem;
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
    }

    tbody tr:hover td {
      background: #f8fafc;
    }

    tbody tr:last-child td {
      border-bottom: 0;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 0.2rem 0.56rem;
      font-size: 0.74rem;
      border: 1px solid var(--line);
      background: #fff;
      color: #334155;
      font-weight: 600;
    }

    .pill.ok { background: #f0fdf4; border-color: #bbf7d0; color: var(--valid); }
    .pill.warn { background: #fffbeb; border-color: #fde68a; color: var(--warn); }
    .pill.error { background: #fef2f2; border-color: #fecaca; color: var(--error); }
    .pill.info { background: #eff6ff; border-color: #bfdbfe; color: var(--info); }

    .split {
      display: grid;
      gap: 0.9rem;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }

    .tag-editor {
      display: grid;
      gap: 0.65rem;
    }

    .tag-row {
      display: grid;
      grid-template-columns: minmax(150px, 0.9fr) minmax(180px, 1fr) auto;
      gap: 0.55rem;
      align-items: end;
    }

    .tag-row .secondary-button {
      min-width: 4.6rem;
    }

    pre {
      margin: 0;
      border-radius: 10px;
      border: 1px solid var(--line);
      background: #f8fafc;
      color: #1e293b;
      padding: 0.76rem;
      overflow-x: auto;
      font-size: 0.81rem;
      line-height: 1.55;
    }

    input:not([type]),
    input[type="text"],
    input[type="search"],
    input[type="number"],
    input[type="email"],
    input[type="url"],
    input[type="password"],
    textarea,
    select {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 9px;
      background: #fff;
      color: var(--text);
      padding: 0.58rem 0.72rem;
      font-size: 0.89rem;
      line-height: 1.4;
      min-height: 2.55rem;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    textarea {
      min-height: 7rem;
      line-height: 1.6;
      resize: vertical;
    }

    input:focus,
    textarea:focus,
    select:focus {
      border-color: #93c5fd;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
      outline: none;
    }

    input[readonly] {
      background: #f8fafc;
      color: #334155;
    }

    select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5.5 7.75L10 12.25L14.5 7.75' stroke='%2364748B' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.65rem center;
      background-size: 0.9rem;
      padding-right: 2rem;
    }

    select[multiple] {
      appearance: auto;
      background-image: none;
      padding-right: 0.72rem;
      min-height: 6.2rem;
    }

    label {
      display: block;
      margin: 0;
      font-size: 0.82rem;
      color: #334155;
      font-weight: 700;
    }

    .form-stack { display: grid; gap: 0.95rem; }
    .field { display: grid; gap: 0.34rem; }

    .form-stack > .panel.soft {
      padding: 1rem;
    }

    .field.inline {
      grid-template-columns: auto 1fr;
      align-items: center;
      gap: 0.52rem;
    }

    .checkbox { width: auto; margin: 0; }

    .filters {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 0.6rem;
      align-items: end;
    }

    .empty-state {
      border: 1px dashed #cbd5e1;
      border-radius: 11px;
      background: #f8fafc;
      color: var(--muted);
      padding: 1rem;
      font-size: 0.88rem;
      line-height: 1.6;
    }

    details {
      border: 1px solid var(--line);
      border-radius: 10px;
      background: #fff;
      padding: 0.55rem 0.72rem;
    }

    summary {
      cursor: pointer;
      font-weight: 700;
      color: #0f172a;
      list-style: none;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    summary::-webkit-details-marker {
      display: none;
    }

    summary::before {
      content: "▸";
      color: #64748b;
      font-size: 0.78rem;
      line-height: 1;
      transform: translateY(-1px);
      transition: transform 0.15s ease;
    }

    details[open] > summary::before {
      transform: rotate(90deg) translateX(1px);
    }

    ul.clean {
      margin: 0;
      padding-left: 1.05rem;
      display: grid;
      gap: 0.3rem;
      font-size: 0.9rem;
    }

    .mt-2 { margin-top: 0.8rem; }
    .mt-3 { margin-top: 0.9rem; }

    .footer-status {
      margin-top: 0.3rem;
      border-top: 1px solid var(--line);
      padding-top: 0.65rem;
      color: #64748b;
      font-size: 0.78rem;
    }

    .graph-layout {
      display: grid;
      gap: 0.9rem;
      grid-template-columns: minmax(0, 1.7fr) minmax(320px, 1fr);
      align-items: start;
    }

    .graph-panel { min-height: 620px; }

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

    #graph-canvas:active { cursor: grabbing; }

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

    .graph-detail-grid { display: grid; gap: 0.62rem; }

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

    .graph-type-list { display: grid; gap: 0.45rem; }

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

    :focus-visible {
      outline: 3px solid #bfdbfe;
      outline-offset: 2px;
    }

    @media (max-width: 980px) {
      .page-shell { grid-template-columns: 1fr; }
      .sidebar { position: static; }
      .filters { grid-template-columns: 1fr 1fr; }
      .graph-layout { grid-template-columns: 1fr; }
    }

    @media (max-width: 760px) {
      .topbar-inner { padding: 0.7rem 0.8rem; }
      .page-shell { padding: 0.8rem; }
      .tag-row { grid-template-columns: 1fr; }
      table { min-width: 640px; }
      th, td { font-size: 0.8rem; }
      .filters { grid-template-columns: 1fr; }
      .graph-toolbar {
        grid-template-columns: 1fr 1fr;
      }
      .graph-toolbar-label,
      #graph-search {
        grid-column: 1 / span 2;
      }
      .graph-canvas-shell { height: 460px; }
      .form-actions { justify-content: stretch; }
      .form-actions > * { flex: 1 1 auto; }
    }
  </style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <strong>Cataloga</strong>
      <span>リソースレジストリ</span>
    </div>
    <form class="searchbar" method="get" action="/resources">
      <input type="search" name="q" placeholder="リソースを検索..." value="<?= h((string) ($_GET['q'] ?? '')) ?>">
      <button type="submit" class="secondary-button">検索</button>
    </form>
  </div>
</header>

<div class="page-shell">
  <aside class="sidebar">
    <nav>
      <a href="/" class="<?= ($currentPath ?? '') === '/' ? 'active' : '' ?>">ダッシュボード</a>
      <a href="/resources" class="<?= str_starts_with((string) ($currentPath ?? ''), '/resources') || str_starts_with((string) ($currentPath ?? ''), '/entities') ? 'active' : '' ?>">リソース</a>
      <a href="/dependencies" class="<?= str_starts_with((string) ($currentPath ?? ''), '/dependencies') || str_starts_with((string) ($currentPath ?? ''), '/relations') ? 'active' : '' ?>">依存関係</a>
      <a href="/changes" class="<?= str_starts_with((string) ($currentPath ?? ''), '/changes') ? 'active' : '' ?>">変更</a>
      <a href="/type-packs" class="<?= str_starts_with((string) ($currentPath ?? ''), '/type-packs') || str_starts_with((string) ($currentPath ?? ''), '/domain-packs') ? 'active' : '' ?>">タイプパック</a>
      <a href="/validation" class="<?= ($currentPath ?? '') === '/validation' ? 'active' : '' ?>">検証</a>
      <a href="/settings" class="<?= ($currentPath ?? '') === '/settings' ? 'active' : '' ?>">設定</a>
    </nav>
  </aside>

  <main class="workspace">
    <?= $content ?>
    <div class="footer-status">Cataloga ・ ローカルワークスペース ・ すべての書き込みはドラフト変更を経由</div>
  </main>
</div>
</body>
</html>
