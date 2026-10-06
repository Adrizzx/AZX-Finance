/**
 * AZX-Finance - Sistema de Validaciones
 */

const Validations = {
    init() {
        this.initFormValidations();
        this.initDateValidations();
        this.initAmountValidations();
    },

    // Mostrar error en campo
    showError(input, message) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        
        // Buscar el contenedor del campo (puede ser input-group o mb-3)
        let container = input.closest('.input-group') || input.parentNode;
        
        // Buscar o crear el contenedor del formulario para el feedback
        let formGroup = input.closest('.mb-3') || input.closest('.mb-4') || container;
        
        // Remover cualquier feedback existente para este input
        const inputId = input.id || input.name;
        const existingFeedback = formGroup.querySelector(`.invalid-feedback[data-for="${inputId}"]`);
        if (existingFeedback) {
            existingFeedback.textContent = message;
            return;
        }
        
        // Crear nuevo feedback
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.setAttribute('data-for', inputId);
        feedback.textContent = message;
        
        // Insertar después del input o input-group
        if (input.closest('.input-group')) {
            input.closest('.input-group').insertAdjacentElement('afterend', feedback);
        } else {
            input.insertAdjacentElement('afterend', feedback);
        }
    },

    // Limpiar error
    clearError(input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        
        // Buscar y remover feedback
        const inputId = input.id || input.name;
        let formGroup = input.closest('.mb-3') || input.closest('.mb-4') || input.parentNode;
        const feedbacks = formGroup.querySelectorAll(`.invalid-feedback[data-for="${inputId}"]`);
        feedbacks.forEach(fb => fb.remove());
    },

    // Validar campo individual
    validateField(input) {
        const value = input.value.trim();
        const type = input.type;
        const name = input.name;

        // Campos requeridos
        if (input.hasAttribute('required') && !value) {
            this.showError(input, 'Este campo es obligatorio');
            return false;
        }

        // Validar campos numéricos (montos)
        if (type === 'number') {
            const num = parseFloat(value);
            
            if (value && isNaN(num)) {
                this.showError(input, 'Debe ingresar un número válido');
                return false;
            }
            
            // Montos no pueden ser negativos
            if (name.includes('monto') || name.includes('cuota') || name.includes('valor') || 
                name === 'monto_total' || name === 'monto_objetivo' || name === 'monto_pago') {
                if (num < 0) {
                    this.showError(input, 'El monto no puede ser negativo');
                    return false;
                }
                if (num === 0 && input.hasAttribute('required')) {
                    this.showError(input, 'El monto debe ser mayor a 0');
                    return false;
                }
            }
            
            // Tasa de interés entre 0 y 100
            if (name === 'tasa_interes' && value) {
                if (num < 0 || num > 100) {
                    this.showError(input, 'La tasa debe estar entre 0% y 100%');
                    return false;
                }
            }
        }

        // Validar fechas
        if (type === 'date' && value) {
            const fecha = new Date(value);
            if (isNaN(fecha.getTime())) {
                this.showError(input, 'Fecha inválida');
                return false;
            }
        }

        // Validar texto (no solo espacios)
        if (type === 'text' && input.hasAttribute('required')) {
            if (!value || value.length < 2) {
                this.showError(input, 'Mínimo 2 caracteres');
                return false;
            }
        }

        this.clearError(input);
        return true;
    },

    // Validar relación de fechas
    validateDateRange(fechaInicioInput, fechaFinInput, allowEqual = true) {
        if (!fechaInicioInput || !fechaFinInput) return true;
        
        const fechaInicio = fechaInicioInput.value;
        const fechaFin = fechaFinInput.value;
        
        if (!fechaInicio || !fechaFin) return true;
        
        const inicio = new Date(fechaInicio);
        const fin = new Date(fechaFin);
        
        if (allowEqual) {
            if (fin < inicio) {
                this.showError(fechaFinInput, 'La fecha final no puede ser anterior a la fecha de inicio');
                return false;
            }
        } else {
            if (fin <= inicio) {
                this.showError(fechaFinInput, 'La fecha final debe ser posterior a la fecha de inicio');
                return false;
            }
        }
        
        this.clearError(fechaFinInput);
        return true;
    },

    // Inicializar validaciones de formularios
    initFormValidations() {
        document.querySelectorAll('form').forEach(form => {
            // Validación en tiempo real
            form.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => {
                    if (input.classList.contains('is-invalid')) {
                        this.validateField(input);
                    }
                });
            });

            // Validación al enviar
            form.addEventListener('submit', (e) => {
                let isValid = true;
                
                form.querySelectorAll('input, select, textarea').forEach(input => {
                    if (!this.validateField(input)) {
                        isValid = false;
                    }
                });

                // Validar rangos de fechas
                const fechaInicio = form.querySelector('[name="fecha_inicio"]');
                const fechaVencimiento = form.querySelector('[name="fecha_vencimiento"]');
                const fechaLimite = form.querySelector('[name="fecha_limite"]');
                
                if (fechaInicio && fechaVencimiento) {
                    if (!this.validateDateRange(fechaInicio, fechaVencimiento)) {
                        isValid = false;
                    }
                }
                
                if (fechaInicio && fechaLimite) {
                    if (!this.validateDateRange(fechaInicio, fechaLimite)) {
                        isValid = false;
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Scroll al primer error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.focus();
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    // Mostrar alerta
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Formulario incompleto',
                            text: 'Por favor, corrige los errores antes de continuar',
                            confirmButtonColor: '#4f46e5'
                        });
                    }
                }
            });
        });
    },

    // Validaciones específicas de fechas en tiempo real
    initDateValidations() {
        // Deudas: fecha_vencimiento debe ser >= fecha_inicio
        this.setupDatePairValidation('fecha_inicio', 'fecha_vencimiento');
        // Metas: fecha_limite debe ser >= fecha_inicio
        this.setupDatePairValidation('fecha_inicio', 'fecha_limite');
    },

    setupDatePairValidation(startName, endName) {
        document.querySelectorAll(`[name="${startName}"]`).forEach(startInput => {
            const form = startInput.closest('form');
            if (!form) return;
            
            const endInput = form.querySelector(`[name="${endName}"]`);
            if (!endInput) return;
            
            // Al cambiar fecha inicio, actualizar mínimo de fecha fin
            startInput.addEventListener('change', () => {
                if (startInput.value) {
                    endInput.setAttribute('min', startInput.value);
                    this.validateDateRange(startInput, endInput);
                }
            });
            
            // Al cambiar fecha fin, validar
            endInput.addEventListener('change', () => {
                this.validateDateRange(startInput, endInput);
            });
            
            // Establecer min inicial
            if (startInput.value) {
                endInput.setAttribute('min', startInput.value);
            }
        });
    },

    // Validaciones de montos
    initAmountValidations() {
        document.querySelectorAll('input[type="number"]').forEach(input => {
            // Prevenir valores negativos
            input.addEventListener('input', () => {
                const name = input.name;
                if (name.includes('monto') || name.includes('cuota') || name.includes('valor') || 
                    name === 'monto_total' || name === 'monto_objetivo' || name === 'monto_pago') {
                    if (parseFloat(input.value) < 0) {
                        input.value = Math.abs(parseFloat(input.value));
                    }
                }
            });

            // Prevenir teclas de signo negativo
            input.addEventListener('keydown', (e) => {
                const name = input.name;
                if (name.includes('monto') || name.includes('cuota') || name.includes('valor') || 
                    name === 'monto_total' || name === 'monto_objetivo' || name === 'monto_pago') {
                    if (e.key === '-' || e.key === 'e' || e.key === 'E') {
                        e.preventDefault();
                    }
                }
            });

            // Agregar atributo min si es monto
            const name = input.name;
            if (name.includes('monto') || name.includes('cuota') || name.includes('valor') || 
                name === 'monto_total' || name === 'monto_objetivo' || name === 'monto_pago') {
                input.setAttribute('min', '0');
            }
        });
    }
};

// Inicializar cuando DOM esté listo
document.addEventListener('DOMContentLoaded', () => Validations.init());
