(function () {
    // Anti double-submit
    const disableParticipationButton = (form) => {
        const trigger = form.querySelector('[data-role="submit-trigger"]');
        if (trigger) {
            trigger.disabled = true;
            trigger.textContent = 'Patientez…';
        }
    };

    // Main logic: handle confirmation click
    document.addEventListener('click', function (ev) {
        const confirmBtn = ev.target.closest('.js-confirm-participation');
        if (!confirmBtn) return;

        const modalEl = confirmBtn.closest('.modal');
        if (!modalEl) return;

        const tripIdMatch = modalEl.id.match(/confirmParticiper-(\d+)/);
        if (!tripIdMatch) return;
        const tripId = tripIdMatch[1];

        const form = document.getElementById('participer-form-' + tripId);
        if (!form) return;

        const hiddenPlacesInput = form.querySelector('input[name="places"]');
        if (hiddenPlacesInput) {
            hiddenPlacesInput.value = 1; // Force 1 place
        } else {
            // As a fallback, create it if it does not exist
            const newHiddenInput = document.createElement('input');
            newHiddenInput.type = 'hidden';
            newHiddenInput.name = 'places';
            newHiddenInput.value = 1; // Force 1 place
            form.appendChild(newHiddenInput);
        }
        
        disableParticipationButton(form);

        // Close modal
        if (window.bootstrap && bootstrap.Modal) {
            const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            instance.hide();
        }

        form.submit();
    });

    // Fallback for old browsers or if Bootstrap JS fails
    document.addEventListener('click', function (ev) {
        const trigger = ev.target.closest('[data-role="submit-trigger"]');
        if (!trigger) return;

        if (window.bootstrap && bootstrap.Modal) return;

        const form = trigger.closest('form');
        if (!form) return;

        if (confirm("Êtes-vous sûr de vouloir participer à ce trajet ?")) { // Removed the specific message about places not being managed
            disableParticipationButton(form);
            form.submit();
        }
    });

    // Anti double-submit on the form itself
    document.addEventListener('submit', function (ev) {
        const form = ev.target.closest('form[id^="participer-form-"]');
        if (!form) return;
        disableParticipationButton(form);
    });
})();


//Avis et note

function stars(n) {
  n = Math.max(0, Math.min(5, Number(n)||0));
  return '★'.repeat(n) + '☆'.repeat(5-n);
}

async function loadReviews(driverId, tripId) {
  const box = document.querySelector('#avis-box');
  const head = document.querySelector('#avis-head'); // pour stats
  if (!box) return;

  box.textContent = 'Chargement…';
  try {
    const url = new URL('/ajax/avis_list.php', window.location.origin);
    url.searchParams.set('driver_id', driverId);
    if (tripId) url.searchParams.set('trip_id', tripId);

    const r = await fetch(url);
    const j = await r.json();

    if (!j.ok) throw new Error(j.error || 'Erreur serveur');

    // Stats
    if (head && j.stats) {
      const avg = (j.stats.avg_rating ?? 0).toFixed(1);
      const cnt = j.stats.count ?? 0;
      head.innerHTML = `Note moyenne: <strong>${avg}</strong> / 5 (${cnt} avis)`;
    }

    // Liste
    if (!j.data || !j.data.length) { box.textContent = 'Aucun avis.'; return; }
    box.innerHTML = j.data.map(a => {
      const created = a.created_at ? new Date(a.created_at).toLocaleDateString() : '';
      return `
        <div class="avis-item">
          <div>${stars(a.rating)} <small>${created}</small></div>
          <div>${(a.comment||'').replace(/</g,'&lt;')}</div>
        </div>`;
    }).join('');
  } catch (e) {
    box.textContent = 'Impossible de charger les avis.';
  }
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-action="voir-avis"]');
  if (!btn) return;
  const driverId = +btn.dataset.driverId || 0;
  const tripId   = +btn.dataset.tripId || 0;
  if (!driverId) return;
  loadReviews(driverId, tripId);
});

document.addEventListener('DOMContentLoaded', refreshCredits);
async function refreshCredits(){
  const el = document.querySelector('[data-role="credits"]');
  if (!el) return;
  try {
    const r = await fetch('/ajax/getCredits.php');
    const j = await r.json();
    if (j.ok) el.textContent = j.credits;
  } catch {}
}