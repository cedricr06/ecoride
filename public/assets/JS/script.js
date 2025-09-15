//bloc qui se depli formulaire recherche trajet mobil/tablette
document.addEventListener('DOMContentLoaded', function () {
  const head = document.querySelector('.head');
  const coll = document.getElementById('filtersCollapse');
  if (!head || !coll) return;

  function needMinHeight() {
    // Desktop : min-height d'origine
    if (window.innerWidth >= 992) return 560;

    // Si fermé : pas besoin d'augmenter
    const opening = coll.classList.contains('show') || coll.classList.contains('collapsing');
    if (!opening) return 560;

    // Géométrie réelle à l'écran (inclut bordures/padding)
    const headTop = head.getBoundingClientRect().top + window.scrollY;
    const collBottom = coll.getBoundingClientRect().bottom + window.scrollY;

    // marge de confort
    const needed = Math.ceil(collBottom - headTop) + 16;

    // ne jamais descendre sous la base
    return Math.max(560, needed);
  }

  function apply() {
    head.style.minHeight = needMinHeight() + 'px';
    // on supprime tout ancien padding-bottom qu'on aurait mis
    head.style.paddingBottom = '';
  }

  // initial + un 2e tick pour laisser finir les transitions
  apply();
  requestAnimationFrame(apply);

  // événements Bootstrap + resize + mutation (fallback)
  ['show.bs.collapse','shown.bs.collapse','hide.bs.collapse','hidden.bs.collapse']
    .forEach(evt => coll.addEventListener(evt, apply));
  window.addEventListener('resize', () => requestAnimationFrame(apply));
  new MutationObserver(() => requestAnimationFrame(apply))
    .observe(coll, { attributes: true, attributeFilter: ['class'] });
});





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


