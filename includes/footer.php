<?php
// ============================================================
// includes/footer.php — Closing tags + JS bundles
// ============================================================
?>
    </main><!-- /#mainContent -->
  </div><!-- /#appWrapper -->

  <!-- Toast container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:9999"></div>

  <!-- Loading overlay -->
  <div class="page-loader" id="pageLoader">
    <div class="loader-inner">
      <div class="loader-pulse"></div>
      <p>Loading…</p>
    </div>
  </div>

  <!-- ── Vendor JS ── -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.46.0/dist/apexcharts.min.js"></script>

  <!-- ── App JS ── -->
  <script>
    // Make APP_URL available globally
    const APP_URL = '<?= APP_URL ?>';
    const CURRENT_USER = <?= json_encode(Auth::user()) ?>;
  </script>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <script src="<?= APP_URL ?>/assets/js/ajax.js"></script>

  <?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $s): ?>
      <script src="<?= APP_URL . '/' . $s ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($inlineScript)): ?>
  <script><?= $inlineScript ?></script>
  <?php endif; ?>
</body>
</html>
