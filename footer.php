<?php
// includes/footer.php - Footer del sistema para páginas con sidebar
?>
<!-- Footer adaptado para sidebar -->
<footer class="admin-footer">
    <div class="footer-content">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="footer-left">
                <small class="text-muted">
                    <i class="fas fa-utensils me-1"></i>
                    Sistema de Gestión Gastronómica v2.1.0
                </small>
            </div>
            <div class="footer-right">
                <small class="text-muted">
                    Desarrollado por 
                    <a href="https://cellcomweb.com.ar" 
                       target="_blank" 
                       class="text-decoration-none text-info">
                        Cellcom Technology
                    </a>
                    | © <?php echo date('Y'); ?>
                </small>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer específico para páginas del admin con sidebar */
.admin-footer {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-top: 1px solid #dee2e6;
    padding: 0.75rem 2rem;
    position: relative;
    bottom: 0;
    font-size: 0.8rem;
    color: #6c757d;
    transition: margin-left 0.3s ease-in-out;
}

.admin-footer .footer-content {
    max-width: 100%;
}

.admin-footer a {
    color: #667eea !important;
    font-weight: 500;
    transition: all 0.3s ease;
}

.admin-footer a:hover {
    color: #764ba2 !important;
    text-decoration: underline !important;
}

.admin-footer .text-info {
    color: #667eea !important;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .admin-footer {
        margin-left: 0;
        padding: 1rem;
        text-align: center;
    }
    
    .admin-footer .d-flex {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .admin-footer .footer-left,
    .admin-footer .footer-right {
        text-align: center;
    }
}

@media (max-width: 576px) {
    .admin-footer {
        padding: 0.75rem;
        font-size: 0.75rem;
    }
    
    .admin-footer .footer-left {
        margin-bottom: 0.25rem;
    }
}

/* Para páginas sin sidebar (como el frontend) */
.public-footer {
    background: linear-gradient(135deg, #212529 0%, #343a40 100%);
    color: white;
    padding: 1rem 0;
    margin-top: 2rem;
}

.public-footer .container {
    text-align: center;
}

.public-footer a {
    color: #20c997 !important;
    text-decoration: none;
    font-weight: 500;
}

.public-footer a:hover {
    color: #17a2b8 !important;
    text-decoration: underline;
}

.public-footer small {
    font-size: 0.8rem;
    color: #adb5bd;
}
</style>