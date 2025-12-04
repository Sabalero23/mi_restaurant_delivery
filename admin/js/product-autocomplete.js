// product-autocomplete.js - Versión mejorada para products.php
// Sistema de autocompletado para evitar productos duplicados

class ProductAutocomplete {
    constructor(inputId, suggestionsId) {
        this.inputId = inputId;
        this.suggestionsId = suggestionsId;
        this.input = null;
        this.suggestionsContainer = null;
        this.debounceTimer = null;
        this.existingProducts = [];
        
        // Intentar inicializar inmediatamente
        this.tryInit();
    }
    
    tryInit() {
        this.input = document.getElementById(this.inputId);
        this.suggestionsContainer = document.getElementById(this.suggestionsId);
        
        if (this.input && this.suggestionsContainer) {
            console.log('✅ Autocompletado inicializado correctamente');
            this.setupEventListeners();
        } else {
            console.warn('⚠️ Esperando a que los elementos estén disponibles...');
            // Reintentar después de un momento
            setTimeout(() => this.tryInit(), 100);
        }
    }
    
    setupEventListeners() {
        // Event listener para el input
        this.input.addEventListener('input', (e) => {
            this.handleInput(e.target.value);
        });
        
        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.suggestionsContainer.contains(e.target)) {
                this.hideSuggestions();
            }
        });
        
        // Manejar teclas de navegación
        this.input.addEventListener('keydown', (e) => {
            this.handleKeyboard(e);
        });
        
        // Limpiar sugerencias cuando se cierra el modal
        const modal = document.getElementById('productModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', () => {
                this.hideSuggestions();
            });
        }
    }
    
    handleInput(value) {
        clearTimeout(this.debounceTimer);
        
        if (value.length < 2) {
            this.hideSuggestions();
            return;
        }
        
        // Debounce para no hacer demasiadas peticiones
        this.debounceTimer = setTimeout(() => {
            this.searchProducts(value);
        }, 300);
    }
    
    async searchProducts(searchTerm) {
        try {
            const response = await fetch(`api/get_product_names.php?search=${encodeURIComponent(searchTerm)}`);
            
            if (!response.ok) {
                throw new Error('Error al buscar productos');
            }
            
            const products = await response.json();
            this.showSuggestions(products, searchTerm);
            
        } catch (error) {
            console.error('Error en autocompletado:', error);
            // Mostrar error en consola pero no interrumpir la experiencia del usuario
        }
    }
    
    showSuggestions(products, searchTerm) {
        // Limpiar sugerencias anteriores
        this.suggestionsContainer.innerHTML = '';
        
        if (products.length === 0) {
            this.hideSuggestions();
            return;
        }
        
        // Verificar si el nombre exacto ya existe
        const exactMatch = products.find(p => p.toLowerCase() === searchTerm.toLowerCase());
        
        if (exactMatch) {
            // Mostrar advertencia de duplicado
            const warningDiv = document.createElement('div');
            warningDiv.className = 'autocomplete-warning';
            warningDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡Producto ya existe!</strong>
                <small>Este nombre ya está registrado</small>
            `;
            this.suggestionsContainer.appendChild(warningDiv);
        }
        
        // Crear lista de sugerencias
        products.forEach(productName => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            
            // Resaltar el texto buscado
            const highlighted = this.highlightMatch(productName, searchTerm);
            item.innerHTML = highlighted;
            
            // Click en sugerencia
            item.addEventListener('click', () => {
                this.selectSuggestion(productName);
            });
            
            this.suggestionsContainer.appendChild(item);
        });
        
        this.suggestionsContainer.style.display = 'block';
    }
    
    highlightMatch(text, search) {
        const regex = new RegExp(`(${this.escapeRegex(search)})`, 'gi');
        return text.replace(regex, '<strong>$1</strong>');
    }
    
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    selectSuggestion(productName) {
        this.input.value = productName;
        this.hideSuggestions();
        
        // Mostrar alerta si el producto ya existe
        this.showDuplicateAlert(productName);
    }
    
    showDuplicateAlert(productName) {
        // Crear o actualizar mensaje de advertencia
        let alert = document.getElementById('duplicate-product-alert');
        
        if (!alert) {
            alert = document.createElement('div');
            alert.id = 'duplicate-product-alert';
            alert.className = 'alert alert-warning alert-dismissible fade show mt-2';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Advertencia:</strong> Ya existe un producto con este nombre.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            this.input.parentElement.appendChild(alert);
        }
    }
    
    hideSuggestions() {
        if (this.suggestionsContainer) {
            this.suggestionsContainer.style.display = 'none';
            this.suggestionsContainer.innerHTML = '';
        }
    }
    
    handleKeyboard(e) {
        const items = this.suggestionsContainer.querySelectorAll('.autocomplete-item');
        
        if (items.length === 0) return;
        
        let currentIndex = -1;
        items.forEach((item, index) => {
            if (item.classList.contains('active')) {
                currentIndex = index;
            }
        });
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentIndex = (currentIndex + 1) % items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentIndex = currentIndex <= 0 ? items.length - 1 : currentIndex - 1;
        } else if (e.key === 'Enter' && currentIndex >= 0) {
            e.preventDefault();
            items[currentIndex].click();
            return;
        } else if (e.key === 'Escape') {
            this.hideSuggestions();
            return;
        } else {
            return;
        }
        
        // Actualizar clase active
        items.forEach((item, index) => {
            if (index === currentIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }
}

// Variable global para mantener la instancia
let productAutocompleteInstance = null;

// Función para inicializar el autocompletado
function initProductAutocomplete() {
    // Solo crear una instancia si no existe
    if (!productAutocompleteInstance) {
        productAutocompleteInstance = new ProductAutocomplete('productName', 'product-suggestions');
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProductAutocomplete);
} else {
    // DOM ya está listo
    initProductAutocomplete();
}

// También inicializar cuando se abra el modal (si existe)
document.addEventListener('DOMContentLoaded', function() {
    const productModal = document.getElementById('productModal');
    if (productModal) {
        productModal.addEventListener('shown.bs.modal', function() {
            // Asegurar que el autocompletado esté inicializado
            initProductAutocomplete();
        });
    }
});

console.log('📦 Script de autocompletado cargado');