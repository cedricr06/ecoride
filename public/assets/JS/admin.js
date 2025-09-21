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

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.act-approve, .act-reject');
  if (!btn) return;

  const id = btn.dataset.id;
  const action = btn.classList.contains('act-approve') ? 'approve' : 'reject';

  // Optionnel : demande raison si rejet
  let reason = '';
  if (action === 'reject') {
    reason = prompt("Raison du rejet (optionnel) :") || '';
  }

  btn.disabled = true;

  try {
    const body = new URLSearchParams({
      action,
      id,
      reason,
      moderator_id: '<?= (int)($_SESSION["user"]["id"] ?? 0) ?>',
      csrf: '<?= $csrf ?>'
    });

    const res = await fetch(location.href, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body
    });
    const json = await res.json();

    if (!json.ok) {
      alert('Erreur: ' + (json.error || 'inconnue'));
      btn.disabled = false;
      return;
    }

    // Retire la ligne approuvée/rejetée
    const row = document.getElementById('row-' + id);
    if (row) row.remove();

    // Optionnel : petit toast
    console.log(`Avis ${action} avec succès`, json);

  } catch (err) {
    console.error(err);
    alert('Erreur réseau.');
    btn.disabled = false;
  }
});

