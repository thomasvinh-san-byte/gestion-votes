(function() {
  var btn = document.getElementById('hamburgerBtn');
  var nav = document.getElementById('landingNav');
  if (btn && nav) {
    btn.addEventListener('click', function() {
      var expanded = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', String(expanded));
    });
  }
})();
