// Theme toggle (initial detection handled by theme-init.js in <head>)
(function() {
  var btn = document.getElementById('btnTheme');
  if (btn) btn.addEventListener('click', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var next = isDark ? 'light' : 'dark';
    localStorage.setItem('ag-vote-theme', next);
    document.documentElement.setAttribute('data-theme', next);
  });
})();
