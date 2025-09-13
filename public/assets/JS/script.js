//bloc qui se depli formulaire recherche trajet mobil/tablette
(() => {
  const head = document.querySelector('.head');
  const collapse = document.getElementById('filtersCollapse');
  if (!head || !collapse) return;

  // Après l'ouverture complète → lance le zoom en douceur
  collapse.addEventListener('shown.bs.collapse', () => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        head.classList.add('bg-zoom');
      });
    });
  });

  // Dès le début de la fermeture → on dézoome
  collapse.addEventListener('hide.bs.collapse', () => {
    head.classList.remove('bg-zoom');
  });

  // Sécurité : à la fin de la fermeture, on s'assure que la classe est retirée
  collapse.addEventListener('hidden.bs.collapse', () => {
    head.classList.remove('bg-zoom');
  });
})();





//oeil 
document.querySelectorAll('.eye-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const input = document.getElementById(btn.dataset.target);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.classList.toggle('is-on', show);
    input.focus();
  });
});


