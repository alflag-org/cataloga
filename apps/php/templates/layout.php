<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title ?? 'Cataloga') ?></title>
  <style>
    :root {
      --bg: #f7f8f3;
      --card: #ffffff;
      --text: #15211a;
      --muted: #4d5d54;
      --line: #d6ddd7;
      --accent: #0a7d46;
      --danger: #af1d1d;
    }
    body { font-family: "IBM Plex Sans", "Segoe UI", sans-serif; margin: 0; background: radial-gradient(circle at top right, #dbeed9 0, var(--bg) 45%); color: var(--text); }
    header { border-bottom: 1px solid var(--line); background: #f1f5ee; }
    .container { max-width: 1080px; margin: 0 auto; padding: 1rem; }
    nav { display: flex; gap: 1rem; flex-wrap: wrap; }
    nav a { color: var(--muted); text-decoration: none; padding: 0.4rem 0.6rem; border-radius: 0.4rem; }
    nav a.active, nav a:hover { background: #e3efe3; color: var(--text); }
    h1, h2, h3 { margin-top: 0; font-family: "Merriweather", Georgia, serif; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 0.8rem; padding: 1rem; margin-top: 1rem; }
    .meta { color: var(--muted); font-size: 0.92rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; border-bottom: 1px solid var(--line); padding: 0.6rem 0.4rem; vertical-align: top; }
    pre { background: #f3f7f3; border: 1px solid var(--line); border-radius: 0.5rem; padding: 0.8rem; overflow-x: auto; }
    code { font-family: "JetBrains Mono", monospace; }
    input[type="text"], textarea, select { width: 100%; box-sizing: border-box; padding: 0.6rem; border: 1px solid var(--line); border-radius: 0.4rem; background: #fff; }
    label { display: block; margin-top: 0.8rem; font-weight: 600; }
    .buttons { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-top: 1rem; }
    button, .button-link { background: var(--accent); color: white; border: none; border-radius: 0.4rem; padding: 0.55rem 0.9rem; cursor: pointer; text-decoration: none; display: inline-block; }
    button.secondary, .button-link.secondary { background: #314a3d; }
    button.danger { background: var(--danger); }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
    .pill { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 999px; border: 1px solid var(--line); font-size: 0.84rem; }
    .ok { color: #0f6f3f; }
    .error { color: var(--danger); }
  </style>
</head>
<body>
<header>
  <div class="container">
    <h1>Cataloga v2 PHP</h1>
    <nav>
      <a href="/" class="<?= ($currentPath ?? '') === '/' ? 'active' : '' ?>">Dashboard</a>
      <a href="/entities" class="<?= str_starts_with((string) ($currentPath ?? ''), '/entities') ? 'active' : '' ?>">Entities</a>
      <a href="/relations" class="<?= str_starts_with((string) ($currentPath ?? ''), '/relations') ? 'active' : '' ?>">Relations</a>
      <a href="/changes" class="<?= str_starts_with((string) ($currentPath ?? ''), '/changes') ? 'active' : '' ?>">Changes</a>
      <a href="/validation" class="<?= ($currentPath ?? '') === '/validation' ? 'active' : '' ?>">Validation</a>
      <a href="/git/diff" class="<?= ($currentPath ?? '') === '/git/diff' ? 'active' : '' ?>">Git Diff</a>
    </nav>
  </div>
</header>
<main class="container">
  <?= $content ?>
</main>
</body>
</html>
