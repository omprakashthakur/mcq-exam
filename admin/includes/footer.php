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

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- Custom Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap 5 tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Initialize AdminLTE pushmenu
    const pushmenuBtn = document.querySelector('[data-widget="pushmenu"]');
    const body = document.querySelector('body');
    
    if (pushmenuBtn) {
        pushmenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            body.classList.toggle('sidebar-collapse');
            body.classList.toggle('sidebar-open');
            localStorage.setItem('sidebar-state', body.classList.contains('sidebar-collapse'));
        });
    }

    // Restore sidebar state from localStorage
    const sidebarState = localStorage.getItem('sidebar-state');
    if (sidebarState === 'true') {
        body.classList.add('sidebar-collapse');
    }

    // Auto collapse sidebar on mobile
    function handleResize() {
        if (window.innerWidth <= 768) {
            body.classList.add('sidebar-collapse');
        }
    }

    window.addEventListener('resize', handleResize);
    handleResize(); // Check on initial load

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Initialize any modals
    var modalTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="modal"]'));
    modalTriggerList.forEach(function(modalTriggerEl) {
        var modalId = modalTriggerEl.getAttribute('data-bs-target');
        var modalElement = document.querySelector(modalId);
        if (modalElement) {
            var modal = new bootstrap.Modal(modalElement);
            modalTriggerEl.addEventListener('click', function() {
                modal.show();
            });
        }
    });

    // Sidebar toggle
    document.getElementById('sidebarCollapse')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
    
    // Auto-hide sidebar on mobile when clicking content area
    document.getElementById('content')?.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar')?.classList.remove('active');
        }
    });
});
</script>
</body>
</html>