// /assets/js/trajet.js
(function () {
  // Clic sur "Oui, je confirme" (dans n'importe quel modal portant data-trip-id)
  document.addEventListener('click', function (ev) {
    const confirmBtn = ev.target.closest('.js-confirm');
    if (!confirmBtn) return;

    const modalEl = confirmBtn.closest('.modal[data-trip-id]');
    if (!modalEl) return;
    const tripId = modalEl.getAttribute('data-trip-id');

    const form = document.getElementById('participer-form-' + tripId);
    if (!form) return;

    const trigger = form.querySelector('[data-role="submit-trigger"]');
    if (trigger) {
      trigger.disabled = true;
      trigger.textContent = 'Patientez…';
    }

    // Ferme le modal si Bootstrap est chargé
    if (window.bootstrap && bootstrap.Modal) {
      const instance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      instance.hide();
    }

    form.submit();
  });

  // Anti double-submit au cas où le form est soumis autrement
  document.addEventListener('submit', function (ev) {
    const form = ev.target.closest('form[id^="participer-form-"]');
    if (!form) return;
    const trigger = form.querySelector('[data-role="submit-trigger"]');
    if (trigger) {
      trigger.disabled = true;
      trigger.textContent = 'Patientez…';
    }
  });

  // Fallback si Bootstrap JS n'est pas chargé : remplace l’ouverture du modal par un confirm() natif
  document.addEventListener('click', function (ev) {
    const trigger = ev.target.closest('[data-role="submit-trigger"]');
    if (!trigger) return;

    // Si Bootstrap est présent, on laisse le data-bs-toggle gérer l’ouverture du modal
    if (window.bootstrap && bootstrap.Modal) return;

    const form = trigger.closest('form');
    if (!form) return;

    if (confirm("Êtes-vous sûr de vouloir participer à ce trajet ?")) {
      trigger.disabled = true;
      trigger.textContent = 'Patientez…';
      form.submit();
    }
  });
})();
