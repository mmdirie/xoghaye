<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// Common HTML Footer & Global Modals
// ==========================================================
?>
        </main> <!-- End of .content-body -->

        <!-- Application Footer -->
        <footer class="app-footer">
            <div>
                <strong><?php echo APP_NAME; ?></strong> &copy; <?php echo date('Y'); ?> &mdash; <?php echo APP_TAGLINE; ?>
            </div>
            <div class="text-muted">
                <span>Version <?php echo APP_VERSION; ?> &bull; University Academic Management System</span>
            </div>
        </footer>
    </div> <!-- End of .main-content -->
</div> <!-- End of .app-wrapper -->

<!-- Global Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm Record Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteConfirmForm" method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="modal-body p-4">
                    <p class="mb-2" id="deleteConfirmMessage">Are you sure you want to delete this record? This action cannot be undone.</p>
                    <div class="alert alert-light border mt-3 mb-0">
                        <strong class="text-danger" id="deleteConfirmItemName">Target Record</strong>
                        <div class="small text-muted mt-1">Record ID: <span id="deleteConfirmItemId"></span></div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash-fill me-1"></i> Yes, Delete Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Application Scripts -->
<script src="<?php echo base_url('assets/js/main.js'); ?>"></script>

</body>
</html>
