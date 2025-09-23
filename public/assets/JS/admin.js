(() => {

  function $(id) {

    return document.getElementById(id);

  }

  function normalize(series) {

    const labels = series?.labels || [];

    const values = (series?.values || []).map((v) => Number(v) || 0);

    return { labels, values };

  }

  function drawAxes(ctx, w, h, margin) {

    const m = typeof margin === "number"

      ? { top: margin, right: margin, bottom: margin, left: margin }

      : {

          top: margin?.top ?? 0,

          right: margin?.right ?? 0,

          bottom: margin?.bottom ?? 0,

          left: margin?.left ?? 0,

        };



    ctx.strokeStyle = "#ccc";

    ctx.lineWidth = 1;

    // Y axis

    ctx.beginPath();

    ctx.moveTo(m.left, m.top);

    ctx.lineTo(m.left, h - m.bottom);

    ctx.stroke();

    // X axis

    ctx.beginPath();

    ctx.moveTo(m.left, h - m.bottom);

    ctx.lineTo(w - m.right, h - m.bottom);

    ctx.stroke();

  }



  function drawLineChart(canvas, labels, values) {
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const width = canvas.clientWidth || canvas.offsetWidth || canvas.parentElement?.clientWidth || canvas.width || 300;
    const height = canvas.clientHeight || canvas.offsetHeight || canvas.height || 160;
    const w = (canvas.width = width);
    const h = (canvas.height = height);
    const margin = {
      top: 16,
      right: Math.max(16, Math.round(w * 0.04)),
      bottom: 40,
      left: Math.max(36, Math.round(w * 0.1)),
    };

    ctx.clearRect(0, 0, w, h);
    drawAxes(ctx, w, h, margin);

    if (!values.length) return;

    const max = Math.max(...values);
    const span = Math.max(1, max);
    const plotW = w - margin.left - margin.right;
    const plotH = h - margin.top - margin.bottom;
    const stepX = plotW / Math.max(1, values.length - 1);

    ctx.strokeStyle = "#198754";
    ctx.lineWidth = 2;
    ctx.beginPath();

    values.forEach((v, i) => {
      const x = margin.left + i * stepX;
      const y = margin.top + plotH - (v / span) * plotH;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    ctx.save();
    ctx.font = "12px sans-serif";
    ctx.fillStyle = "#6c757d";
    ctx.textAlign = "center";
    ctx.textBaseline = "top";

    const maxTicks = Math.min(labels.length, 7);
    const step = Math.max(1, Math.ceil(labels.length / maxTicks));

    labels.forEach((label, i) => {
      if (i % step !== 0 && i !== labels.length - 1) return;
      const short = label && label.length > 5 ? label.slice(5) : label || "";
      const x = margin.left + i * stepX;
      ctx.fillText(short, x, h - margin.bottom + 6);
    });

    ctx.textBaseline = "bottom";
    ctx.fillStyle = "#198754";

    values.forEach((v, i) => {
      const x = margin.left + i * stepX;
      const y = margin.top + plotH - (v / span) * plotH;
      ctx.fillText(String(v), x, y - 4);
    });

    ctx.restore();
  }


  
  function drawBarChart(canvas, labels, values) {
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const width = canvas.clientWidth || canvas.offsetWidth || canvas.parentElement?.clientWidth || canvas.width || 300;
    const height = canvas.clientHeight || canvas.offsetHeight || canvas.height || 160;
    const w = (canvas.width = width);
    const h = (canvas.height = height);
    const margin = {
      top: 16,
      right: Math.max(16, Math.round(w * 0.04)),
      bottom: 40,
      left: Math.max(36, Math.round(w * 0.1)),
    };

    ctx.clearRect(0, 0, w, h);
    drawAxes(ctx, w, h, margin);

    if (!values.length) return;

    const max = Math.max(...values, 1);
    const plotW = w - margin.left - margin.right;
    const plotH = h - margin.top - margin.bottom;
    const barCount = values.length;
    const slot = plotW / Math.max(1, barCount);
    const barW = slot * 0.6;
    const pad = (slot - barW) / 2;

    ctx.fillStyle = "#0d6efd";
    ctx.font = "12px sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "bottom";

    values.forEach((v, i) => {
      const x = margin.left + i * slot + pad;
      const barH = (v / max) * plotH;
      const y = margin.top + plotH - barH;
      ctx.fillRect(x, y, barW, barH);
      ctx.fillText(String(v), x + barW / 2, y - 4);
    });

    ctx.save();
    ctx.font = "12px sans-serif";
    ctx.fillStyle = "#6c757d";
    ctx.textAlign = "center";
    ctx.textBaseline = "top";

    const maxTicks = Math.min(labels.length, 7);
    const step = Math.max(1, Math.ceil(labels.length / maxTicks));

    labels.forEach((label, i) => {
      if (i % step !== 0 && i !== labels.length - 1) return;
      const short = label && label.length > 5 ? label.slice(5) : label || "";
      const x = margin.left + i * slot + barW / 2;
      ctx.fillText(short, x, h - margin.bottom + 6);
    });

    ctx.restore();
  }


  let midnightTimer = null;

  function msUntilNextParisRefresh() {

    try {

      const now = new Date();

      const parisNow = new Date(

        now.toLocaleString("en-US", { timeZone: "Europe/Paris" })

      );

      const next = new Date(parisNow);

      next.setHours(24, 5, 0, 0);

      const diff = next.getTime() - parisNow.getTime();

      if (!Number.isFinite(diff) || diff <= 0) {

        return 60 * 60 * 1000;

      }

      return diff;

    } catch (err) {

      return 60 * 60 * 1000;

    }

  }

  function scheduleMidnightRefresh() {

    if (midnightTimer) {

      window.clearTimeout(midnightTimer);

    }

    const delay = msUntilNextParisRefresh();

    midnightTimer = window.setTimeout(() => {

      Promise.resolve(load()).finally(scheduleMidnightRefresh);

    }, delay);

  }

  function render(stats) {

    if (!stats || typeof stats !== "object") {

      return;

    }

    window.__LAST_STATS__ = stats;

    window.__ADMIN_STATS__ = stats;

    try {

      const rides = normalize(stats.rides);

      const revenue = normalize(stats.revenue);

      drawLineChart($("ridesChart"), rides.labels, rides.values);

      drawBarChart($("revenueChart"), revenue.labels, revenue.values);

      const fallbackTotal = revenue.values.reduce((a, b) => a + b, 0);

      const candidate =

        typeof stats.total_revenue === "number"

          ? stats.total_revenue

          : Number(stats.total_revenue);

      const total = Number.isFinite(candidate) ? candidate : fallbackTotal;

      const t = document.getElementById("totalRevenueText");

      if (t) {

        t.textContent = total.toFixed(2);

      }

    } catch (e) {

      /* noop */

    }

  }

  async function load() {

    if (window.__ADMIN_STATS__) {

      render(window.__ADMIN_STATS__);

    }

    const ctx = document.getElementById("admin-stats-ctx");

    if (!ctx) {

      return;

    }

    const baseEndpoint = ctx.dataset.endpoint

      ? ctx.dataset.endpoint

      : window.location.pathname.replace(/\/admin.*/, "") + "/admin/stats";

    const parsedDays = parseInt(ctx.dataset.days || "7", 10);

    const days = Number.isFinite(parsedDays)

      ? Math.max(1, Math.min(31, parsedDays))

      : 7;

    const url = new URL(baseEndpoint, window.location.origin);

    url.searchParams.set("days", String(days));

    try {

      const res = await fetch(url.toString(), {

        credentials: "same-origin",

        headers: { Accept: "application/json" },

        cache: "no-store",

      });

      if (res.ok) {

        const data = await res.json();

        render(data);

      }

    } catch (e) {

      /* ignore offline */

    }

  }

  if (document.readyState === "loading") {

    document.addEventListener("DOMContentLoaded", load);

  } else {

    load();

  }

  scheduleMidnightRefresh();

  document.addEventListener("shown.bs.tab", (ev) => {

    if (ev.target && ev.target.id === "tab-stats-tab") {

      if (window.__LAST_STATS__) {

        render(window.__LAST_STATS__);

      } else {

        load();

      }

    }

  });

  window.addEventListener("resize", () => {

    if (window.__LAST_STATS__) {

      render(window.__LAST_STATS__);

    }

  });

  window.addEventListener("focus", () => {

    Promise.resolve(load()).finally(scheduleMidnightRefresh);

  });

})();

const MOD = (() => {

  const base = {

    endpoint: window.location.pathname,

    count: 0,

    csrf: "",

    moderatorId: 0,

    query: "",

  };

  if (

    window.__ADMIN_MODERATION__ &&

    typeof window.__ADMIN_MODERATION__ === "object"

  ) {

    return { ...base, ...window.__ADMIN_MODERATION__ };

  }

  return base;

})();

document.addEventListener("click", async (e) => {

  const btn = e.target.closest(".act-approve, .act-reject");

  if (!btn) return;

  const ctx = MOD;

  const id = btn.dataset.id;

  if (!id) return;

  const action = btn.classList.contains("act-approve") ? "approve" : "reject";

  let reason = "";

  if (action === "reject") {

    reason = prompt("Raison du rejet (optionnel) :") || "";

  }

  const moderatorId = ctx.moderatorId != null ? String(ctx.moderatorId) : "0";

  const csrf = ctx.csrf || "";

  if (!csrf) {

    alert("Token CSRF manquant.");

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

    const query = ctx.query || "";

    try {

      const url = new URL(base, window.location.origin);

      if (query) {

        url.search = query;

      }

      return url.toString();

    } catch (err) {

      if (!query) return base;

      const join = base.includes("?") ? "&" : "?";

      return base + join + query;

    }

  };

  const endpoint = resolveEndpoint();

  const row = document.getElementById("row-" + id);

  const link = document.getElementById("avis-en-attente-tab");

  btn.disabled = true;

  try {

    const res = await fetch(endpoint, {

      method: "POST",

      headers: { "Content-Type": "application/x-www-form-urlencoded" },

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

      const message =

        json && json.error ? json.error : res.statusText || "Erreur serveur.";

      alert("Erreur: " + message);

      btn.disabled = false;

      return;

    }

    if (typeof ctx.count === "number") {

      ctx.count = Math.max(0, ctx.count - 1);

    }

    if (link) {

      const current = parseInt(link.getAttribute("data-count") || "0", 10);

      const next = Math.max(0, current - 1);

      link.setAttribute("data-count", String(next));

      const textContent = link.textContent || "";

      if (/\(\s*\d+\s*\)/.test(textContent)) {

        link.textContent = textContent.replace(/\(\s*\d+\s*\)/, `(${next})`);

      } else {

        link.textContent = `${textContent.trim()} (${next})`;

      }

    }

    if (row) row.remove();

    const tbody = document.getElementById("pending-tbody");

    if (tbody && !tbody.querySelector("tr")) {

      const empty = document.getElementById("pending-empty");

      if (empty) empty.classList.remove("d-none");

      const tableWrapper = document.getElementById("pending-table-wrapper");

      if (tableWrapper) tableWrapper.classList.add("d-none");

      const pagination = document.getElementById("pending-pagination");

      if (pagination) pagination.classList.add("d-none");

    }

  } catch (err) {

    console.error(err);

    alert("Erreur reseau.");

    btn.disabled = false;

    return;

  }

  btn.disabled = false;

});

