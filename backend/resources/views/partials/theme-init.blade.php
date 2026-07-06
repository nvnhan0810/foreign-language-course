<script>
(function () {
  var key = 'flc-theme';
  var stored = localStorage.getItem(key) || 'system';
  var dark = stored === 'dark' || (stored === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  var meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', dark ? '#1a2332' : '#4361ee');
})();
</script>
