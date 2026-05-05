<?php
$nodes = [];
foreach ($entities as $entity) {
    $nodes[] = [
        'id' => (string) ($entity['id'] ?? ''),
        'name' => (string) ($entity['name'] ?? ''),
        'type' => (string) ($entity['type'] ?? ''),
        'sourcePath' => (string) ($entity['sourcePath'] ?? ''),
    ];
}

$nodeLookup = [];
foreach ($nodes as $node) {
    $nodeLookup[$node['id']] = true;
}

$edges = [];
foreach ($relations as $relation) {
    $from = (string) ($relation['from'] ?? '');
    $to = (string) ($relation['to'] ?? '');
    if ($from === '' || $to === '' || !isset($nodeLookup[$from]) || !isset($nodeLookup[$to])) {
        continue;
    }
    $edges[] = [
        'id' => (string) ($relation['id'] ?? ''),
        'type' => (string) ($relation['type'] ?? ''),
        'from' => $from,
        'to' => $to,
        'sourcePath' => (string) ($relation['sourcePath'] ?? ''),
    ];
}

$graphPayload = [
    'nodes' => $nodes,
    'edges' => $edges,
];
?>

<div class="panel soft">
  <div class="title-row">
    <div class="title-stack">
      <p class="eyebrow">グラフ</p>
      <h2>リソースグラフ</h2>
      <p class="meta">リソースと依存関係を1画面で確認できます。</p>
    </div>
    <div class="actions">
      <a class="secondary-button" href="/resources">リソース</a>
      <a class="secondary-button" href="/dependencies">依存関係</a>
      <a class="secondary-button" href="/changes">変更</a>
    </div>
  </div>

  <div class="metrics">
    <article class="metric-card">
      <span>ノード</span>
      <strong><?= h((string) count($nodes)) ?></strong>
      <p>グラフ上のリソース数。</p>
    </article>
    <article class="metric-card">
      <span>エッジ</span>
      <strong><?= h((string) count($edges)) ?></strong>
      <p>有効な依存関係の数。</p>
    </article>
    <article class="metric-card">
      <span>リソース種別</span>
      <strong><?= h((string) count($typeCounts)) ?></strong>
      <p>ノード種別の数。</p>
    </article>
  </div>
</div>

<div class="graph-layout">
  <section class="panel graph-panel">
    <div class="graph-toolbar">
      <label for="graph-search" class="graph-toolbar-label">ノード検索</label>
      <input id="graph-search" type="search" placeholder="id, name, type" />
      <button type="button" id="graph-fit" class="secondary-button">全体表示</button>
      <button type="button" id="graph-reset" class="secondary-button">リセット</button>
    </div>
    <p id="graph-status" class="meta">グラフを読み込み中...</p>
    <div id="graph-canvas-shell" class="graph-canvas-shell">
      <canvas id="graph-canvas" aria-label="Registry graph canvas"></canvas>
    </div>
    <p class="meta">ノードをドラッグして移動、背景ドラッグでパン、ホイールでズームできます。</p>
  </section>

  <aside class="panel graph-side">
    <div class="title-row">
      <div class="title-stack">
        <p class="eyebrow">インスペクタ</p>
        <h3>選択中のリソース</h3>
      </div>
    </div>
    <div id="node-detail" class="graph-detail empty-state">ノードを選択すると詳細を表示します。</div>

    <div class="title-row mt-3">
      <div class="title-stack">
        <p class="eyebrow">リソース種別</p>
        <h3>分布</h3>
      </div>
    </div>
    <?php if ($typeCounts === []): ?>
      <p class="empty-state">表示できるリソースがありません。</p>
    <?php else: ?>
      <div class="graph-type-list">
        <?php foreach ($typeCounts as $type => $count): ?>
          <div class="graph-type-item">
            <span><?= h((string) $type) ?></span>
            <strong><?= h((string) $count) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>
</div>

<script id="graph-data" type="application/json"><?= h((string) json_encode($graphPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></script>
<script>
(() => {
  const payloadNode = document.getElementById('graph-data');
  const canvas = document.getElementById('graph-canvas');
  const shell = document.getElementById('graph-canvas-shell');
  const status = document.getElementById('graph-status');
  const searchInput = document.getElementById('graph-search');
  const fitButton = document.getElementById('graph-fit');
  const resetButton = document.getElementById('graph-reset');
  const detail = document.getElementById('node-detail');

  if (!payloadNode || !canvas || !shell || !status || !searchInput || !fitButton || !resetButton || !detail) {
    return;
  }

  const payload = JSON.parse(payloadNode.textContent || '{"nodes":[],"edges":[]}');
  const rawNodes = Array.isArray(payload.nodes) ? payload.nodes : [];
  const rawEdges = Array.isArray(payload.edges) ? payload.edges : [];

  const dpr = window.devicePixelRatio || 1;
  const ctx = canvas.getContext('2d');
  if (!ctx) {
    status.textContent = 'Canvas is not available in this browser.';
    return;
  }

  let width = 0;
  let height = 0;
  let panX = 0;
  let panY = 0;
  let zoom = 1;
  let hoveredId = null;
  let selectedId = null;
  let draggingNodeId = null;
  let panning = false;
  let lastPointerX = 0;
  let lastPointerY = 0;

  const palette = [
    '#0f766e', '#0369a1', '#7c3aed', '#b45309', '#be123c', '#0ea5e9', '#1d4ed8', '#15803d', '#9333ea', '#dc2626'
  ];

  const typeColors = new Map();
  const getTypeColor = (type) => {
    if (!typeColors.has(type)) {
      typeColors.set(type, palette[typeColors.size % palette.length]);
    }
    return typeColors.get(type);
  };

  const nodes = rawNodes.map((node, index) => ({
    id: String(node.id || ''),
    name: String(node.name || ''),
    type: String(node.type || ''),
    sourcePath: String(node.sourcePath || ''),
    x: (Math.cos(index * 0.5) * 160) + ((index % 7) * 22),
    y: (Math.sin(index * 0.7) * 120) + ((index % 5) * 18),
    vx: 0,
    vy: 0,
    radius: 9,
  })).filter((node) => node.id !== '');

  const nodeById = new Map(nodes.map((node) => [node.id, node]));

  const edges = rawEdges
    .map((edge) => ({
      id: String(edge.id || ''),
      type: String(edge.type || ''),
      sourceId: String(edge.from || ''),
      targetId: String(edge.to || ''),
      sourcePath: String(edge.sourcePath || ''),
    }))
    .filter((edge) => nodeById.has(edge.sourceId) && nodeById.has(edge.targetId));

  const adjacency = new Map();
  nodes.forEach((node) => adjacency.set(node.id, new Set()));
  edges.forEach((edge) => {
    adjacency.get(edge.sourceId).add(edge.targetId);
    adjacency.get(edge.targetId).add(edge.sourceId);
  });

  const toWorld = (sx, sy) => {
    return {
      x: (sx - width / 2 - panX) / zoom,
      y: (sy - height / 2 - panY) / zoom,
    };
  };

  const nearestNode = (sx, sy) => {
    const point = toWorld(sx, sy);
    let winner = null;
    let best = Infinity;
    for (const node of nodes) {
      const dx = point.x - node.x;
      const dy = point.y - node.y;
      const dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < node.radius + 4 && dist < best) {
        best = dist;
        winner = node;
      }
    }
    return winner;
  };

  const updateDetail = () => {
    if (!selectedId || !nodeById.has(selectedId)) {
      detail.className = 'graph-detail empty-state';
      detail.innerHTML = 'ノードを選択すると詳細を表示します。';
      return;
    }

    const node = nodeById.get(selectedId);
    const neighbors = Array.from(adjacency.get(node.id) || []);
    const neighborButtons = neighbors
      .slice(0, 30)
      .map((id) => `<button type="button" class="chip" data-node-id="${id}">${id}</button>`)
      .join('');

    detail.className = 'graph-detail';
    detail.innerHTML = `
      <div class="graph-detail-grid">
        <div><span class="eyebrow">ID</span><p class="mono">${node.id}</p></div>
        <div><span class="eyebrow">タイプ</span><p>${node.type || 'unknown'}</p></div>
        <div><span class="eyebrow">名前</span><p>${node.name || '(no name)'}</p></div>
        <div><span class="eyebrow">接続数</span><p>${neighbors.length}</p></div>
        <div><span class="eyebrow">パス</span><p class="mono">${node.sourcePath || '-'}</p></div>
      </div>
      <div class="mt-2">
        <p class="eyebrow">隣接ノード</p>
        <div class="chip-row">${neighborButtons || '<span class="meta">隣接ノードなし</span>'}</div>
      </div>
    `;

    detail.querySelectorAll('[data-node-id]').forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = button.getAttribute('data-node-id');
        if (!targetId || !nodeById.has(targetId)) {
          return;
        }
        selectedId = targetId;
        status.textContent = `選択: ${targetId}`;
        updateDetail();
      });
    });
  };

  const resize = () => {
    const rect = shell.getBoundingClientRect();
    width = Math.max(420, Math.floor(rect.width));
    height = Math.max(420, Math.floor(rect.height));
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  };

  const fitGraph = () => {
    if (nodes.length === 0) {
      return;
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (const node of nodes) {
      minX = Math.min(minX, node.x);
      minY = Math.min(minY, node.y);
      maxX = Math.max(maxX, node.x);
      maxY = Math.max(maxY, node.y);
    }

    const graphWidth = Math.max(140, maxX - minX);
    const graphHeight = Math.max(140, maxY - minY);
    const sx = (width * 0.82) / graphWidth;
    const sy = (height * 0.82) / graphHeight;
    zoom = Math.max(0.35, Math.min(2.4, Math.min(sx, sy)));

    const cx = (minX + maxX) / 2;
    const cy = (minY + maxY) / 2;
    panX = -cx * zoom;
    panY = -cy * zoom;
  };

  const resetGraph = () => {
    zoom = 1;
    panX = 0;
    panY = 0;
    selectedId = null;
    hoveredId = null;
    updateDetail();
  };

  const stepPhysics = () => {
    if (nodes.length > 240) {
      return;
    }

    const repulsion = 4600;
    const springLength = 68;
    const springStrength = 0.016;
    const centerPull = 0.0009;

    for (let i = 0; i < nodes.length; i++) {
      const a = nodes[i];
      for (let j = i + 1; j < nodes.length; j++) {
        const b = nodes[j];
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const distSq = (dx * dx) + (dy * dy) + 0.02;
        const dist = Math.sqrt(distSq);
        const force = repulsion / distSq;
        const fx = (dx / dist) * force;
        const fy = (dy / dist) * force;
        a.vx -= fx;
        a.vy -= fy;
        b.vx += fx;
        b.vy += fy;
      }
    }

    for (const edge of edges) {
      const source = nodeById.get(edge.sourceId);
      const target = nodeById.get(edge.targetId);
      if (!source || !target) {
        continue;
      }
      const dx = target.x - source.x;
      const dy = target.y - source.y;
      const dist = Math.sqrt(dx * dx + dy * dy) + 0.001;
      const delta = dist - springLength;
      const force = springStrength * delta;
      const fx = (dx / dist) * force;
      const fy = (dy / dist) * force;
      source.vx += fx;
      source.vy += fy;
      target.vx -= fx;
      target.vy -= fy;
    }

    for (const node of nodes) {
      if (draggingNodeId === node.id) {
        node.vx = 0;
        node.vy = 0;
        continue;
      }
      node.vx -= node.x * centerPull;
      node.vy -= node.y * centerPull;
      node.vx *= 0.86;
      node.vy *= 0.86;
      node.x += node.vx;
      node.y += node.vy;
    }
  };

  const draw = () => {
    ctx.clearRect(0, 0, width, height);

    ctx.save();
    ctx.translate((width / 2) + panX, (height / 2) + panY);
    ctx.scale(zoom, zoom);

    const selectedNeighbors = new Set(selectedId ? Array.from(adjacency.get(selectedId) || []) : []);

    for (const edge of edges) {
      const source = nodeById.get(edge.sourceId);
      const target = nodeById.get(edge.targetId);
      if (!source || !target) {
        continue;
      }

      const isActive = selectedId && (edge.sourceId === selectedId || edge.targetId === selectedId);
      ctx.strokeStyle = isActive ? '#0ea5e9' : '#cbd5e1';
      ctx.lineWidth = isActive ? 2.2 : 1.2;
      ctx.beginPath();
      ctx.moveTo(source.x, source.y);
      ctx.lineTo(target.x, target.y);
      ctx.stroke();
    }

    for (const node of nodes) {
      const isSelected = selectedId === node.id;
      const isNeighbor = selectedNeighbors.has(node.id);
      const isHovered = hoveredId === node.id;
      const baseColor = getTypeColor(node.type || 'unknown');

      ctx.fillStyle = baseColor;
      ctx.beginPath();
      ctx.arc(node.x, node.y, node.radius + (isSelected ? 3 : 0), 0, Math.PI * 2);
      ctx.fill();

      if (isSelected || isNeighbor || isHovered) {
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 1.4;
        ctx.beginPath();
        ctx.arc(node.x, node.y, node.radius + 4, 0, Math.PI * 2);
        ctx.stroke();
      }

      if (isSelected || isHovered || zoom > 1.35) {
        ctx.fillStyle = '#0f172a';
        ctx.font = '12px "JetBrains Mono", monospace';
        ctx.fillText(node.id, node.x + 12, node.y - 10);
      }
    }

    ctx.restore();
  };

  const animate = () => {
    stepPhysics();
    draw();
    requestAnimationFrame(animate);
  };

  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim().toLowerCase();
    if (q === '') {
      hoveredId = null;
      status.textContent = `ノード ${nodes.length} 件 / エッジ ${edges.length} 件`;
      return;
    }

    const match = nodes.find((node) =>
      node.id.toLowerCase().includes(q) ||
      node.name.toLowerCase().includes(q) ||
      node.type.toLowerCase().includes(q)
    );

    if (match) {
      hoveredId = match.id;
      status.textContent = `Match: ${match.id}`;
    } else {
      hoveredId = null;
      status.textContent = `\"${q}\" に一致するノードがありません`;
    }
  });

  searchInput.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') {
      return;
    }

    const q = searchInput.value.trim().toLowerCase();
    const match = nodes.find((node) =>
      node.id.toLowerCase().includes(q) ||
      node.name.toLowerCase().includes(q) ||
      node.type.toLowerCase().includes(q)
    );

    if (!match) {
      return;
    }

    selectedId = match.id;
    hoveredId = match.id;
    panX = -match.x * zoom;
    panY = -match.y * zoom;
    updateDetail();
    status.textContent = `選択: ${match.id}`;
  });

  fitButton.addEventListener('click', () => {
    fitGraph();
  });

  resetButton.addEventListener('click', () => {
    resetGraph();
    status.textContent = `ノード ${nodes.length} 件 / エッジ ${edges.length} 件`;
  });

  canvas.addEventListener('pointerdown', (event) => {
    canvas.setPointerCapture(event.pointerId);
    lastPointerX = event.offsetX;
    lastPointerY = event.offsetY;

    const hit = nearestNode(event.offsetX, event.offsetY);
    if (hit) {
      draggingNodeId = hit.id;
      selectedId = hit.id;
      updateDetail();
      status.textContent = `選択: ${hit.id}`;
      return;
    }

    panning = true;
  });

  canvas.addEventListener('pointermove', (event) => {
    const sx = event.offsetX;
    const sy = event.offsetY;

    if (draggingNodeId && nodeById.has(draggingNodeId)) {
      const target = nodeById.get(draggingNodeId);
      const world = toWorld(sx, sy);
      target.x = world.x;
      target.y = world.y;
      return;
    }

    if (panning) {
      panX += sx - lastPointerX;
      panY += sy - lastPointerY;
      lastPointerX = sx;
      lastPointerY = sy;
      return;
    }

    const hit = nearestNode(sx, sy);
    hoveredId = hit ? hit.id : null;
  });

  canvas.addEventListener('pointerup', (event) => {
    if (canvas.hasPointerCapture(event.pointerId)) {
      canvas.releasePointerCapture(event.pointerId);
    }
    draggingNodeId = null;
    panning = false;
  });

  canvas.addEventListener('pointercancel', () => {
    draggingNodeId = null;
    panning = false;
  });

  canvas.addEventListener('wheel', (event) => {
    event.preventDefault();

    const oldZoom = zoom;
    const delta = event.deltaY < 0 ? 1.08 : 0.92;
    zoom = Math.max(0.25, Math.min(4, zoom * delta));

    const cursorX = event.offsetX - (width / 2);
    const cursorY = event.offsetY - (height / 2);
    const wx = (cursorX - panX) / oldZoom;
    const wy = (cursorY - panY) / oldZoom;
    panX = cursorX - (wx * zoom);
    panY = cursorY - (wy * zoom);
  }, { passive: false });

  window.addEventListener('resize', () => {
    resize();
    fitGraph();
  });

  resize();
  fitGraph();
  updateDetail();
  status.textContent = `ノード ${nodes.length} 件 / エッジ ${edges.length} 件`;
  animate();
})();
</script>
