            </div> <!-- .content-container -->
        </div> <!-- .content-scroll -->
    </main>
</div> <!-- .layout-container -->

<script>
(function() {
  // IntersectionObserver for staggered card reveal
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
