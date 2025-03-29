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

</div> <!-- Close main-content div -->
        
        <!-- Footer -->
        <footer class="py-4 mt-auto">
            <div class="container">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="mb-2 mb-md-0">
                        <strong>Copyright &copy; <?php echo date('Y'); ?> MCQ Exam System</strong>
                    </div>
                    <div class="text-muted">
                        Software developed by <strong>Om Thakur</strong>
                    </div>
                </div>
            </div>
        </footer>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
        </script>
    </body>
</html>