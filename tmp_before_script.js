//bloc qui se depli formulaire recherche trajet mobil/tablette
document.addEventListener('DOMContentLoaded', function () {
  const head = document.querySelector('.head');
  const coll = document.getElementById('filtersCollapse');
  if (!head || !coll) return;

  function measuredHeight() {
    const box = coll.querySelector('.form-box');
    return Math.max(
      coll.scrollHeight,
      coll.offsetHeight,
      box ? box.scrollHeight : 0
    );
  }

  function adjust() {
    const isMobile = window.innerWidth < 992;
    const isOpening = coll.classList.contains('show') || coll.classList.contains('collapsing');
    head.style.paddingBottom = (isMobile && isOpening) ? (measuredHeight() + 24) + 'px' : '';
  }

  adjust();                      // initial
  requestAnimationFrame(adjust); // 2e tick si besoin

  ['show.bs.collapse','shown.bs.collapse','hide.bs.collapse','hidden.bs.collapse']
    .forEach(evt => coll.addEventListener(evt, adjust));

  window.addEventListener('resize', () => requestAnimationFrame(adjust));

  // Fallback si classes changent sans event
  new MutationObserver(() => requestAnimationFrame(adjust))
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



