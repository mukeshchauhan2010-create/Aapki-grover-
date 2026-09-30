<script>
(function(){
  // Open Google login in a centered popup window instead of a full redirect.
  document.querySelectorAll('[data-google-login]').forEach(function(link){
    link.addEventListener('click', function(e){
      // Only hijack on desktop-sized screens that support popups well.
      if (window.innerWidth < 500) return; // let mobile use full redirect
      e.preventDefault();
      var base = link.getAttribute('href');
      var url  = base + (base.indexOf('?') > -1 ? '&' : '?') + 'popup=1';
      var w = 480, h = 640;
      var left = window.screenX + Math.max(0, (window.outerWidth  - w) / 2);
      var top  = window.screenY + Math.max(0, (window.outerHeight - h) / 2);
      var popup = window.open(url, 'aapki_google_login',
        'width='+w+',height='+h+',left='+left+',top='+top+',resizable=yes,scrollbars=yes');
      if (!popup) { window.location = base; return; } // popup blocked → fallback

      // When the popup finishes login it posts a message; then we reload.
      function onMsg(ev){
        if (ev.data === 'aapki-login-success') {
          window.removeEventListener('message', onMsg);
          try { popup.close(); } catch(_){}
          window.location.reload();
        }
      }
      window.addEventListener('message', onMsg);

      // Fallback: if the popup closes on its own, reload to reflect login state.
      var timer = setInterval(function(){
        if (popup.closed) { clearInterval(timer); window.location.reload(); }
      }, 800);
    });
  });
})();
</script>
