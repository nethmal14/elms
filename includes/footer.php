            </div> <!-- .content-container -->

            <footer class="site-footer">
              <div class="footer-inner">
                <div class="footer-brand">
                  <div class="logo-mark">E</div>
                  <span style="font-size:0.8rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($site_name ?? 'Elms') ?></span>
                </div>
                <p class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name ?? 'Elms') ?>. All rights reserved.</p>
              </div>
            </footer>

        </div> <!-- .content-scroll -->
    </main>
</div> <!-- .layout-container -->

<script>
(function() {
  // Mobile Sidebar Toggle
  function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('open');
    }
  }
  function closeSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar && overlay) {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    }
  }
  // Expose globally
  window.toggleSidebar = toggleSidebar;
  window.closeSidebar = closeSidebar;

  // IntersectionObserver for staggered reveal
  var io = new IntersectionObserver(function(entries) {
    entries.forEach(function(e, i) {
      if (e.isIntersecting) {
        setTimeout(function() { e.target.classList.add('is-visible'); }, i * 50);
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.04 });
  
  document.querySelectorAll('.card, .glass-panel').forEach(function(el) {
    io.observe(el);
  });
})();
</script>
</body>
</html>
