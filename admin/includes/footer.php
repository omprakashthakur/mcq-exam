</div>
        </section>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            Software developed by <strong>Om Thakur</strong>
        </div>
        <strong>Copyright &copy; <?php echo date('Y'); ?> MCQ Exam System</strong>
    </footer>
</div>

<!-- Scripts -->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Custom Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dropdowns
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(dropdown => {
        new bootstrap.Dropdown(dropdown, {
            boundary: 'window'
        });
    });

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });

    // Initialize AdminLTE sidebar
    const pushmenuBtn = document.querySelector('[data-widget="pushmenu"]');
    if (pushmenuBtn) {
        pushmenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-collapse');
            localStorage.setItem('sidebar-state', document.body.classList.contains('sidebar-collapse'));
        });
    }

    // Restore sidebar state
    if (localStorage.getItem('sidebar-state') === 'true') {
        document.body.classList.add('sidebar-collapse');
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>