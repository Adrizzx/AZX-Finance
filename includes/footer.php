        </div><!-- /.page-content -->
    </main><!-- /.main-content -->
    
    <!-- Quick Add Modal -->
    <div class="modal fade" id="quickAddModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Rápido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="quick-add-options">
                        <a href="<?= BASE_URL ?>pages/ingresos.php?add=1" class="quick-add-item">
                            <div class="icon bg-success">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <span>Nuevo Ingreso</span>
                        </a>
                        <a href="<?= BASE_URL ?>pages/gastos.php?add=1" class="quick-add-item">
                            <div class="icon bg-danger">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <span>Nuevo Gasto</span>
                        </a>
                        <a href="<?= BASE_URL ?>pages/metas.php?add=1" class="quick-add-item">
                            <div class="icon bg-primary">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <span>Nueva Meta</span>
                        </a>
                        <a href="<?= BASE_URL ?>pages/deudas.php?add=1" class="quick-add-item">
                            <div class="icon bg-warning">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <span>Nueva Deuda</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="liveToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="fas fa-check-circle text-success me-2"></i>
                <strong class="me-auto">AZX-Finance</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- JS personalizado -->
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
    <script src="<?= BASE_URL ?>assets/js/validations.js"></script>
    
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
            <script src="<?= BASE_URL ?>assets/js/<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
