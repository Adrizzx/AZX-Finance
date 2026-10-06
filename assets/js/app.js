/**
 * AZX-Finance - JavaScript Principal
 */

// Configurar colores de Chart.js según el tema
function updateChartColors() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#f1f5f9' : '#1e293b';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';
    
    if (typeof Chart !== 'undefined') {
        Chart.defaults.color = textColor;
        Chart.defaults.plugins.legend.labels.color = textColor;
        Chart.defaults.plugins.title.color = textColor;
        Chart.defaults.scale.ticks.color = textColor;
        Chart.defaults.scale.grid.color = gridColor;
    }
}

// Inicializar colores de charts
document.addEventListener('DOMContentLoaded', () => {
    updateChartColors();
});

// Configuración global
const App = {
    baseUrl: document.querySelector('base')?.href || window.location.origin + '/PROYECTOS/PRY_VACACIONES/PRY_AZXFinance/',
    currencySymbol: '$',
    
    // Inicialización
    init() {
        this.initTheme();
        this.initSidebar();
        this.initTooltips();
        this.initToasts();
        this.initDeleteConfirm();
    },
    
    // Modo Oscuro/Claro
    initTheme() {
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        // Cargar tema guardado o preferencia del sistema
        const savedTheme = localStorage.getItem('azx-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme) {
            html.setAttribute('data-theme', savedTheme);
        } else if (prefersDark) {
            html.setAttribute('data-theme', 'dark');
        }
        
        // Toggle de tema
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const currentTheme = html.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('azx-theme', newTheme);
                
                // Actualizar colores de los gráficos
                updateChartColors();
                
                // Animación suave
                html.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                setTimeout(() => {
                    html.style.transition = '';
                }, 300);
            });
        }
        
        // Escuchar cambios en preferencia del sistema
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('azx-theme')) {
                html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                updateChartColors();
            }
        });
    },
    
    // Sidebar toggle
    initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOpen = document.getElementById('sidebarOpen');
        const sidebarClose = document.getElementById('sidebarClose');
        
        if (sidebarOpen) {
            sidebarOpen.addEventListener('click', () => {
                sidebar.classList.add('active');
            });
        }
        
        if (sidebarClose) {
            sidebarClose.addEventListener('click', () => {
                sidebar.classList.remove('active');
            });
        }
        
        // Cerrar sidebar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && 
                sidebar.classList.contains('active') && 
                !sidebar.contains(e.target) && 
                !sidebarOpen?.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    },
    
    // Tooltips de Bootstrap
    initTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
    },
    
    // Sistema de toasts
    initToasts() {
        this.toastEl = document.getElementById('liveToast');
        if (this.toastEl) {
            this.toast = new bootstrap.Toast(this.toastEl);
        }
    },
    
    // Mostrar toast
    showToast(message, type = 'success') {
        if (!this.toastEl) return;
        
        const icon = this.toastEl.querySelector('.toast-header i');
        const body = this.toastEl.querySelector('.toast-body');
        
        icon.className = 'fas me-2';
        if (type === 'success') {
            icon.classList.add('fa-check-circle', 'text-success');
        } else if (type === 'error') {
            icon.classList.add('fa-times-circle', 'text-danger');
        } else if (type === 'warning') {
            icon.classList.add('fa-exclamation-triangle', 'text-warning');
        }
        
        body.textContent = message;
        this.toast.show();
    },
    
    // Confirmación de eliminación
    initDeleteConfirm() {
        document.querySelectorAll('[data-delete]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const url = btn.dataset.delete;
                const name = btn.dataset.name || 'este registro';
                
                const result = await Swal.fire({
                    title: '¿Estás seguro?',
                    text: `¿Deseas eliminar ${name}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                });
                
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        });
    },
    
    // Formatear moneda
    formatMoney(amount, symbol = this.currencySymbol) {
        return symbol + parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    
    // Formatear fecha
    formatDate(dateStr, format = 'short') {
        const date = new Date(dateStr);
        const options = format === 'long' 
            ? { year: 'numeric', month: 'long', day: 'numeric' }
            : { year: 'numeric', month: '2-digit', day: '2-digit' };
        return date.toLocaleDateString('es-ES', options);
    },
    
    // Calcular porcentaje
    percentage(value, total) {
        if (total === 0) return 0;
        return Math.round((value / total) * 100);
    },
    
    // Colores para gráficas
    chartColors: [
        '#4f46e5', '#22c55e', '#ef4444', '#f59e0b', '#06b6d4',
        '#8b5cf6', '#ec4899', '#f97316', '#14b8a6', '#64748b'
    ],
    
    // Crear gráfica de dona
    createDoughnutChart(canvasId, labels, data, colors = null) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#f1f5f9' : '#1e293b';
        
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors || this.chartColors.slice(0, data.length),
                    borderWidth: 0,
                    spacing: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: textColor
                        }
                    }
                }
            }
        });
    },
    
    // Crear gráfica de barras
    createBarChart(canvasId, labels, datasets, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#f1f5f9' : '#1e293b';
        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';
        
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: textColor
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                ...options
            }
        });
    },
    
    // Crear gráfica de línea
    createLineChart(canvasId, labels, datasets, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDark ? '#f1f5f9' : '#1e293b';
        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';
        
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets.map((ds, i) => ({
                    ...ds,
                    borderColor: ds.borderColor || this.chartColors[i],
                    backgroundColor: ds.backgroundColor || this.chartColors[i] + '20',
                    fill: ds.fill !== undefined ? ds.fill : true,
                    tension: 0.4
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: textColor
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                ...options
            }
        });
    },
    
    // Petición AJAX
    async fetch(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Error en la petición');
            }
            
            return data;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    },
    
    // POST request
    async post(url, body) {
        return this.fetch(url, {
            method: 'POST',
            body: JSON.stringify(body)
        });
    },
    
    // Validar formulario
    validateForm(form) {
        const inputs = form.querySelectorAll('[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => App.init());

// Exportar para uso global
window.App = App;
