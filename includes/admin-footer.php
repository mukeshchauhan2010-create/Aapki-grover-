    </div><!-- /admin-content -->
  </div><!-- /admin-main -->
</div><!-- /admin-shell -->

<script>
(function () {
  // Sidebar toggle for mobile
  const toggle  = document.getElementById('menuToggle');
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (toggle) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', function () {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }

  // Auto-dismiss flash alerts after 4 s
  const alerts = document.querySelectorAll('.ap-alert');
  alerts.forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 500);
    }, 4000);
  });

  // Variant builder (products page)
  window.addVariant = function () {
    const wrap = document.getElementById('variants');
    if (!wrap) return;
    const i = wrap.querySelectorAll('.ap-variant-row').length;
    wrap.insertAdjacentHTML('beforeend', `
      <div class="ap-variant-row">
        <input class="ap-input" name="variant_label[]" placeholder="e.g. 500 GM">
        <select class="ap-select" name="variant_type[]">
          <option value="g">g</option>
          <option value="kg">kg</option>
          <option value="pc">pc</option>
        </select>
        <input class="ap-input" name="variant_qty[]" type="number" step="0.001" value="500" placeholder="Qty">
        <input class="ap-input" name="variant_mrp[]" type="number" step="0.01" placeholder="MRP">
        <input class="ap-input" name="variant_price[]" type="number" step="0.01" placeholder="Price">
        <input class="ap-input" name="variant_stock[]" type="number" step="0.001" placeholder="Stock">
        <label class="ap-check-row"><input type="radio" name="default_variant" value="${i}"> Default</label>
      </div>`);
  };
})();
</script>
</body>
</html>
