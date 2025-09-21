(() => {
  function $(id){ return document.getElementById(id); }

  function normalize(series){
    const labels = series?.labels || [];
    const values = (series?.values || []).map(v => Number(v) || 0);
    return { labels, values };
  }

  function drawAxes(ctx, w, h, margin){
    ctx.strokeStyle = '#ccc';
    ctx.lineWidth = 1;
    // Y axis
    ctx.beginPath(); ctx.moveTo(margin, margin); ctx.lineTo(margin, h - margin); ctx.stroke();
    // X axis
    ctx.beginPath(); ctx.moveTo(margin, h - margin); ctx.lineTo(w - margin, h - margin); ctx.stroke();
  }

  function drawLineChart(canvas, labels, values){
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width = canvas.clientWidth;
    const h = canvas.height = canvas.clientHeight;
    const m = 28; // margin
    ctx.clearRect(0,0,w,h);
    drawAxes(ctx, w, h, m);
    if (!values.length) return;

    const max = Math.max(...values);
    const min = 0;
    const span = Math.max(1, max - min);
    const stepX = (w - 2*m) / Math.max(1, values.length - 1);

    ctx.strokeStyle = '#198754';
    ctx.lineWidth = 2;
    ctx.beginPath();
    values.forEach((v,i) => {
      const x = m + i*stepX;
      const y = (h - m) - ((v - min)/span)*(h - 2*m);
      if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();
  }

  function drawBarChart(canvas, labels, values){
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width = canvas.clientWidth;
    const h = canvas.height = canvas.clientHeight;
    const m = 28;
    ctx.clearRect(0,0,w,h);
    drawAxes(ctx, w, h, m);
    if (!values.length) return;

    const max = Math.max(...values, 1);
    const barCount = values.length;
    const plotW = (w - 2*m);
    const barW = plotW / Math.max(1, barCount) * 0.6;
    const gap = (plotW / Math.max(1, barCount)) * 0.4;

    ctx.fillStyle = '#0d6efd';
    values.forEach((v,i) => {
      const x = m + i*(barW+gap) + gap*0.2;
      const barH = ((v)/max) * (h - 2*m);
      const y = (h - m) - barH;
      ctx.fillRect(x, y, barW, barH);
    });
  }

  function render(stats){
    try {
      const rides = normalize(stats.rides);
      const revenue = normalize(stats.revenue);
      drawLineChart($('ridesChart'), rides.labels, rides.values);
      drawBarChart($('revenueChart'), revenue.labels, revenue.values);
      const total = Number(stats.total_revenue || revenue.values.reduce((a,b)=>a+b,0)).toFixed(2);
      const t = document.getElementById('totalRevenueText');
      if (t) t.textContent = total;
    } catch (e) { /* noop */ }
  }

  async function load(){
    // First render with embedded data if any (no network)
    if (window.__ADMIN_STATS__) render(window.__ADMIN_STATS__);
    // Then try to refresh from endpoint
    try {
      const res = await fetch(window.location.pathname.replace(/\/admin.*/, '') + '/admin/stats', { credentials: 'same-origin' });
      if (res.ok) {
        const data = await res.json();
        render(data);
      }
    } catch (e) { /* ignore offline */ }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})();

const el = document.getElementById('admin-mod-ctx');
const MOD = el ? {
  endpoint: el.dataset.endpoint || '/admin',
  count: parseInt(el.dataset.count || '0', 10),
  csrf: el.dataset.csrf || '',
  moderatorId: parseInt(el.dataset.mid || '0', 10),
} : { endpoint: '/admin', count: 0, csrf: '', moderatorId: 0 };



document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.act-approve, .act-reject');
  if (!btn) return;

  const ctx = MOD;
  const id = btn.dataset.id;
  if (!id) return;

  const action = btn.classList.contains('act-approve') ? 'approve' : 'reject';
  let reason = '';
  if (action === 'reject') {
    reason = prompt('Raison du rejet (optionnel) :') || '';
  }

  const moderatorId = ctx.moderatorId != null ? String(ctx.moderatorId) : '0';
  const csrf = ctx.csrf || '';
  if (!csrf) {
    alert('Token CSRF manquant.');
    return;
  }

  const params = new URLSearchParams({
    action,
    id,
    reason,
    moderator_id: moderatorId,
    csrf,
  });

  const resolveEndpoint = () => {
    const base = ctx.endpoint || window.location.href;
    const query = ctx.query || '';
    try {
      const url = new URL(base, window.location.origin);
      if (query) {
        url.search = query;
      }
      return url.toString();
    } catch (err) {
      if (!query) return base;
      const join = base.includes('?') ? '&' : '?';
      return base + join + query;
    }
  };

  const endpoint = ctx.endpoint || window.location.pathname;
  const row = document.getElementById('row-' + id);
  const link = document.getElementById('avis-en-attente-tab');

  btn.disabled = true;

  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params,
    });

    const text = await res.text();
    let json = null;
    if (text) {
      try {
        json = JSON.parse(text);
      } catch (err) {
        /* ignore non-json */
      }
    }

    const success = res.ok && (!json || json.ok !== false);

    if (!success) {
      const message = (json && json.error) ? json.error : (res.statusText || 'Erreur serveur.');
      alert('Erreur: ' + message);
      btn.disabled = false;
      return;
    }

    if (typeof ctx.count === 'number') {
      ctx.count = Math.max(0, ctx.count - 1);
    }

    if (link) {
      const current = parseInt(link.getAttribute('data-count') || '0', 10);
      const next = Math.max(0, current - 1);
      link.setAttribute('data-count', String(next));
      const textContent = link.textContent || '';
      if (/\(\s*\d+\s*\)/.test(textContent)) {
        link.textContent = textContent.replace(/\(\s*\d+\s*\)/, `(${next})`);
      } else {
        link.textContent = `${textContent.trim()} (${next})`;
      }
    }

    if (row) row.remove();

    const tbody = document.getElementById('pending-tbody');
    if (tbody && !tbody.querySelector('tr')) {
      const empty = document.getElementById('pending-empty');
      if (empty) empty.classList.remove('d-none');
      const tableWrapper = document.getElementById('pending-table-wrapper');
      if (tableWrapper) tableWrapper.classList.add('d-none');
      const pagination = document.getElementById('pending-pagination');
      if (pagination) pagination.classList.add('d-none');
    }
  } catch (err) {
    console.error(err);
    alert('Erreur reseau.');
    btn.disabled = false;
    return;
  }

  btn.disabled = false;
});