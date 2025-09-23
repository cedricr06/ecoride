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