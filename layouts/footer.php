</div> <!-- End of container-fluid -->

        <!-- Footer -->
        <footer class="footer mt-auto py-3 bg-light">
            <div class="container text-center">
                <span class="text-muted">
                    <?php echo getSetting('system_title') ?? SITE_NAME; ?> &copy; <?php echo date('Y'); ?>
                </span>
            </div>
        </footer>

        <!-- Bootstrap Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <!-- DataTables -->
        <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
        
        <!-- Custom JavaScript -->
        <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                "language": {
                    "url": "assets/js/dataTables." + "<?php echo getCurrentLanguage(); ?>" + ".json"
                }
            });

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Auto-hide flash messages
            setTimeout(function() {
                $('.alert-dismissible').alert('close');
            }, 5000);

            // Confirm delete actions
            $('.confirm-delete').on('click', function(e) {
                if (!confirm($(this).data('confirm-message') || '<?php echo translate("confirm_delete"); ?>')) {
                    e.preventDefault();
                }
            });

            // Handle form validation
            (function () {
                'use strict'
                var forms = document.querySelectorAll('.needs-validation');
                Array.prototype.slice.call(forms).forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            })();

            // Handle sidebar toggle on mobile
            $('.navbar-toggler').on('click', function() {
                $('body').toggleClass('sidebar-open');
            });

            // Handle active menu items
            $('.nav-link').each(function() {
                if (window.location.href.indexOf($(this).attr('href')) > -1) {
                    $(this).addClass('active');
                    if ($(this).closest('.dropdown-menu').length) {
                        $(this).closest('.dropdown').find('.dropdown-toggle').addClass('active');
                    }
                }
            });
        });

        // Function to format numbers
        function formatNumber(number) {
            return new Intl.NumberFormat('<?php echo getCurrentLanguage(); ?>').format(number);
        }

        // Function to format dates
        function formatDate(date) {
            return new Date(date).toLocaleDateString('<?php echo getCurrentLanguage(); ?>', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Function to show loading spinner
        function showLoading() {
            $('#loadingSpinner').show();
        }

        // Function to hide loading spinner
        function hideLoading() {
            $('#loadingSpinner').hide();
        }

        // Global AJAX setup
        $.ajaxSetup({
            beforeSend: function() {
                showLoading();
            },
            complete: function() {
                hideLoading();
            },
            error: function(xhr, status, error) {
                alert('<?php echo translate("error_occurred"); ?>: ' + error);
            }
        });
        </script>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="position-fixed top-50 start-50 translate-middle" style="display: none; z-index: 9999;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden"><?php echo translate('loading'); ?></span>
            </div>
        </div>
    </body>
</html>
