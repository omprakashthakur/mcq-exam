<?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
  
<?php else: ?>
    <footer class="admin-footer py-3">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">© <?php echo date('Y'); ?> MCQ Exam System</span>
                <span class="text-muted">Version 1.0</span>
            </div>
        </div>
    </footer>
<?php endif; ?>
</div> <!-- Close content/container div -->
        </section>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            Software developed by <strong>Om Thakur</strong>
        </div>
        <strong>Copyright &copy; <?php echo date('Y'); ?> MCQ Exam System</strong>
    </footer>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Custom Scripts -->
<script>
$(document).ready(function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);

    // Initialize any Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Initialize any Bootstrap popovers
    $('[data-toggle="popover"]').popover();
});
</script>
</body>
</html>