<?php
/**
 * AZX-Finance - Simuladores Financieros
 */
$pageTitle = 'Simuladores';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-flask text-purple me-2" style="color: #6f42c1;"></i>Simuladores Financieros</h1>
        <p>Herramientas para proyectar y planificar tu futuro financiero</p>
    </div>
</div>

<div class="row g-4">
    <!-- Simulador de Interés Compuesto -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-success border-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>Interés Compuesto
                </h5>
                <small>Proyecta el crecimiento de tu dinero</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Capital Inicial</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="ic_capital" value="1000000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Aporte Mensual</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="ic_aporte" value="200000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tasa de Interés Anual (%)</label>
                        <input type="number" class="form-control" id="ic_tasa" value="10" step="0.1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Período (años)</label>
                        <input type="number" class="form-control" id="ic_anios" value="10">
                    </div>
                </div>
                <button class="btn btn-success w-100 mt-3" onclick="calcularInteresCompuesto()">
                    <i class="fas fa-calculator me-1"></i> Calcular
                </button>
                
                <div id="ic_resultado" class="mt-4" style="display: none;">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Total Depositado</small>
                                <h5 class="text-primary mb-0" id="ic_depositado"></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Intereses Ganados</small>
                                <h5 class="text-success mb-0" id="ic_intereses"></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Valor Final</small>
                                <h5 class="text-success mb-0" id="ic_final"></h5>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container mt-4" style="height: 200px;">
                        <canvas id="icChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Simulador de Ahorro para Meta -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-primary border-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bullseye me-2"></i>Ahorro para Meta
                </h5>
                <small>¿Cuánto debo ahorrar para alcanzar mi meta?</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Meta de Ahorro</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="meta_objetivo" value="30000000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ya tengo ahorrado</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="meta_actual" value="1000000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plazo (meses)</label>
                        <input type="number" class="form-control" id="meta_meses" value="24">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Rendimiento Anual (%)</label>
                        <input type="number" class="form-control" id="meta_tasa" value="5" step="0.1">
                    </div>
                </div>
                <button class="btn btn-primary w-100 mt-3" onclick="calcularMeta()">
                    <i class="fas fa-calculator me-1"></i> Calcular
                </button>
                
                <div id="meta_resultado" class="mt-4" style="display: none;">
                    <div class="alert alert-primary mb-0">
                        <h5 class="alert-heading mb-1">
                            <i class="fas fa-lightbulb me-2"></i>Debes ahorrar:
                        </h5>
                        <h3 class="mb-0" id="meta_mensual"></h3>
                        <span class="opacity-75">mensualmente</span>
                        <hr>
                        <p class="mb-0 small">
                            <span id="meta_detalle"></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Simulador de Préstamo -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-warning border-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-hand-holding-usd me-2"></i>Simulador de Préstamo
                </h5>
                <small>Calcula la cuota mensual de un crédito</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Monto del Préstamo</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="prestamo_monto" value="20000000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tasa de Interés Anual (%)</label>
                        <input type="number" class="form-control" id="prestamo_tasa" value="24" step="0.1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Plazo (meses)</label>
                        <input type="number" class="form-control" id="prestamo_meses" value="36">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sistema</label>
                        <select class="form-select" id="prestamo_sistema">
                            <option value="frances">Francés (cuota fija)</option>
                            <option value="aleman">Alemán (amortización fija)</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-warning w-100 mt-3" onclick="calcularPrestamo()">
                    <i class="fas fa-calculator me-1"></i> Calcular
                </button>
                
                <div id="prestamo_resultado" class="mt-4" style="display: none;">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Cuota Mensual</small>
                                <h5 class="text-primary mb-0" id="prestamo_cuota"></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Total Intereses</small>
                                <h5 class="text-danger mb-0" id="prestamo_intereses"></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Total a Pagar</small>
                                <h5 class="text-warning mb-0" id="prestamo_total"></h5>
                            </div>
                        </div>
                    </div>
                    <div class="table-container" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm mb-0" id="tabla_amortizacion">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>#</th>
                                    <th>Cuota</th>
                                    <th>Capital</th>
                                    <th>Interés</th>
                                    <th>Saldo</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Simulador Capacidad de Endeudamiento -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-info border-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-balance-scale me-2"></i>Capacidad de Endeudamiento
                </h5>
                <small>¿Cuánto crédito puedo tomar?</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Ingreso Mensual Neto</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="cap_ingreso" value="3500000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Deudas Actuales (cuotas mensuales)</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="cap_deudas" value="500000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">% Máximo de endeudamiento</label>
                        <input type="number" class="form-control" id="cap_porcentaje" value="40">
                        <small class="text-muted">Recomendado: 30-40%</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tasa de Interés Anual (%)</label>
                        <input type="number" class="form-control" id="cap_tasa" value="24" step="0.1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Plazo del Crédito (meses)</label>
                        <input type="number" class="form-control" id="cap_meses" value="48">
                    </div>
                </div>
                <button class="btn btn-info w-100 mt-3" onclick="calcularCapacidad()">
                    <i class="fas fa-calculator me-1"></i> Calcular
                </button>
                
                <div id="cap_resultado" class="mt-4" style="display: none;">
                    <div class="row g-3">
                        <div class="col-6 text-center">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Cuota Máxima Disponible</small>
                                <h5 class="text-info mb-0" id="cap_cuota_max"></h5>
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Monto Máximo de Crédito</small>
                                <h5 class="text-success mb-0" id="cap_monto_max"></h5>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0" id="cap_mensaje"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Simulador de Inflación -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-danger border-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-area me-2"></i>Impacto de la Inflación
                </h5>
                <small>¿Cuánto perderá valor tu dinero?</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Monto Actual</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="inf_monto" value="10000000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Inflación Anual Estimada (%)</label>
                        <input type="number" class="form-control" id="inf_tasa" value="8" step="0.1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Años a proyectar</label>
                        <input type="number" class="form-control" id="inf_anios" value="5">
                    </div>
                </div>
                <button class="btn btn-danger w-100 mt-3" onclick="calcularInflacion()">
                    <i class="fas fa-calculator me-1"></i> Calcular
                </button>
                
                <div id="inf_resultado" class="mt-4" style="display: none;">
                    <div class="alert alert-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="d-block opacity-75">Poder adquisitivo en <span id="inf_anios_texto"></span> años:</small>
                                <h4 class="mb-0" id="inf_valor_real"></h4>
                            </div>
                            <div class="text-end">
                                <small class="d-block opacity-75">Pérdida de valor:</small>
                                <h5 class="mb-0 text-danger" id="inf_perdida"></h5>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Esto significa que necesitarás <strong id="inf_necesario"></strong> en el futuro para comprar lo que hoy compras con <strong id="inf_hoy"></strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Simulador Regla 50/30/20 -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header card-header-purple border-0">
                <h5 class="card-title mb-0">
                    <i class="fas fa-percent me-2"></i>Regla 50/30/20
                </h5>
                <small>Distribución ideal del presupuesto</small>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Ingreso Mensual Neto</label>
                    <div class="input-group">
                        <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                        <input type="number" class="form-control" id="regla_ingreso" value="4000000">
                    </div>
                </div>
                <button class="btn w-100 mt-2" style="background: #6f42c1; color: white;" onclick="calcularRegla()">
                    <i class="fas fa-calculator me-1"></i> Calcular Distribución
                </button>
                
                <div id="regla_resultado" class="mt-4" style="display: none;">
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-primary">50% - Necesidades</strong>
                                    <strong class="text-primary" id="regla_50"></strong>
                                </div>
                                <small class="text-muted">Vivienda, servicios, alimentación, transporte</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="bg-warning bg-opacity-10 rounded p-3">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-warning">30% - Deseos</strong>
                                    <strong class="text-warning" id="regla_30"></strong>
                                </div>
                                <small class="text-muted">Entretenimiento, restaurantes, hobbies</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-success">20% - Ahorro e Inversión</strong>
                                    <strong class="text-success" id="regla_20"></strong>
                                </div>
                                <small class="text-muted">Fondo de emergencia, inversiones, retiro</small>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container" style="height: 150px;">
                        <canvas id="reglaChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función para formatear dinero
function formatMoney(amount) {
    return App.formatMoney ? App.formatMoney(amount) : '<?= APP_CURRENCY_SYMBOL ?> ' + amount.toLocaleString('es-CO');
}

// Función para obtener colores según el tema
function getChartColors() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    return {
        textColor: isDark ? '#f1f5f9' : '#1e293b',
        gridColor: isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)'
    };
}

// Simulador de Interés Compuesto
let icChartInstance = null;
function calcularInteresCompuesto() {
    const capital = parseFloat(document.getElementById('ic_capital').value) || 0;
    const aporteMensual = parseFloat(document.getElementById('ic_aporte').value) || 0;
    const tasaAnual = parseFloat(document.getElementById('ic_tasa').value) / 100;
    const anios = parseInt(document.getElementById('ic_anios').value) || 1;
    
    const meses = anios * 12;
    const tasaMensual = tasaAnual / 12;
    
    // Cálculo del valor futuro con aportes
    let valorFuturo = capital;
    const dataValor = [capital];
    const dataDepositado = [capital];
    const labels = ['Inicio'];
    
    for (let i = 1; i <= meses; i++) {
        valorFuturo = valorFuturo * (1 + tasaMensual) + aporteMensual;
        if (i % 12 === 0) {
            labels.push('Año ' + (i / 12));
            dataValor.push(Math.round(valorFuturo));
            dataDepositado.push(capital + (aporteMensual * i));
        }
    }
    
    const totalDepositado = capital + (aporteMensual * meses);
    const intereses = valorFuturo - totalDepositado;
    
    document.getElementById('ic_depositado').textContent = formatMoney(totalDepositado);
    document.getElementById('ic_intereses').textContent = formatMoney(intereses);
    document.getElementById('ic_final').textContent = formatMoney(valorFuturo);
    document.getElementById('ic_resultado').style.display = 'block';
    
    // Gráfica
    const ctx = document.getElementById('icChart').getContext('2d');
    if (icChartInstance) icChartInstance.destroy();
    
    const colors = getChartColors();
    
    icChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Valor Acumulado',
                data: dataValor,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Depositado',
                data: dataDepositado,
                borderColor: '#0d6efd',
                borderDash: [5, 5],
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top', labels: { color: colors.textColor } } },
            scales: {
                y: { ticks: { color: colors.textColor }, grid: { color: colors.gridColor } },
                x: { ticks: { color: colors.textColor } }
            }
        }
    });
}

// Simulador de Meta de Ahorro
function calcularMeta() {
    const objetivo = parseFloat(document.getElementById('meta_objetivo').value) || 0;
    const actual = parseFloat(document.getElementById('meta_actual').value) || 0;
    const meses = parseInt(document.getElementById('meta_meses').value) || 1;
    const tasaAnual = parseFloat(document.getElementById('meta_tasa').value) / 100;
    const tasaMensual = tasaAnual / 12;
    
    const faltante = objetivo - actual;
    
    // PMT = (FV - PV * (1+r)^n) * r / ((1+r)^n - 1)
    let ahorroMensual;
    if (tasaMensual > 0) {
        const factor = Math.pow(1 + tasaMensual, meses);
        ahorroMensual = (faltante * tasaMensual) / (factor - 1);
    } else {
        ahorroMensual = faltante / meses;
    }
    
    document.getElementById('meta_mensual').textContent = formatMoney(ahorroMensual);
    document.getElementById('meta_detalle').textContent = 
        `En ${meses} meses depositarás ${formatMoney(ahorroMensual * meses)} + ya tienes ${formatMoney(actual)} = Meta de ${formatMoney(objetivo)}`;
    document.getElementById('meta_resultado').style.display = 'block';
}

// Simulador de Préstamo
function calcularPrestamo() {
    const monto = parseFloat(document.getElementById('prestamo_monto').value) || 0;
    const tasaAnual = parseFloat(document.getElementById('prestamo_tasa').value) / 100;
    const meses = parseInt(document.getElementById('prestamo_meses').value) || 1;
    const sistema = document.getElementById('prestamo_sistema').value;
    const tasaMensual = tasaAnual / 12;
    
    let cuotaFija, totalIntereses, totalPagar;
    const amortizacion = [];
    let saldo = monto;
    
    if (sistema === 'frances') {
        // Sistema Francés: cuota fija
        cuotaFija = monto * (tasaMensual * Math.pow(1 + tasaMensual, meses)) / (Math.pow(1 + tasaMensual, meses) - 1);
        
        for (let i = 1; i <= meses; i++) {
            const interes = saldo * tasaMensual;
            const capital = cuotaFija - interes;
            saldo -= capital;
            amortizacion.push({
                mes: i,
                cuota: cuotaFija,
                capital: capital,
                interes: interes,
                saldo: Math.max(0, saldo)
            });
        }
        totalPagar = cuotaFija * meses;
    } else {
        // Sistema Alemán: amortización fija
        const amortizacionFija = monto / meses;
        
        for (let i = 1; i <= meses; i++) {
            const interes = saldo * tasaMensual;
            const cuota = amortizacionFija + interes;
            saldo -= amortizacionFija;
            amortizacion.push({
                mes: i,
                cuota: cuota,
                capital: amortizacionFija,
                interes: interes,
                saldo: Math.max(0, saldo)
            });
        }
        cuotaFija = amortizacion[0].cuota;
        totalPagar = amortizacion.reduce((sum, a) => sum + a.cuota, 0);
    }
    
    totalIntereses = totalPagar - monto;
    
    document.getElementById('prestamo_cuota').textContent = formatMoney(cuotaFija);
    document.getElementById('prestamo_intereses').textContent = formatMoney(totalIntereses);
    document.getElementById('prestamo_total').textContent = formatMoney(totalPagar);
    
    // Tabla de amortización
    const tbody = document.querySelector('#tabla_amortizacion tbody');
    tbody.innerHTML = amortizacion.map(a => `
        <tr>
            <td>${a.mes}</td>
            <td>${formatMoney(a.cuota)}</td>
            <td>${formatMoney(a.capital)}</td>
            <td>${formatMoney(a.interes)}</td>
            <td>${formatMoney(a.saldo)}</td>
        </tr>
    `).join('');
    
    document.getElementById('prestamo_resultado').style.display = 'block';
}

// Simulador de Capacidad de Endeudamiento
function calcularCapacidad() {
    const ingreso = parseFloat(document.getElementById('cap_ingreso').value) || 0;
    const deudasActuales = parseFloat(document.getElementById('cap_deudas').value) || 0;
    const porcentajeMax = parseFloat(document.getElementById('cap_porcentaje').value) / 100;
    const tasaAnual = parseFloat(document.getElementById('cap_tasa').value) / 100;
    const meses = parseInt(document.getElementById('cap_meses').value) || 1;
    const tasaMensual = tasaAnual / 12;
    
    const cuotaMaxTotal = ingreso * porcentajeMax;
    const cuotaDisponible = Math.max(0, cuotaMaxTotal - deudasActuales);
    
    // Calcular monto máximo de crédito basado en cuota disponible
    // Despejando P de: PMT = P * r(1+r)^n / ((1+r)^n - 1)
    const factor = Math.pow(1 + tasaMensual, meses);
    const montoMaximo = cuotaDisponible * ((factor - 1) / (tasaMensual * factor));
    
    document.getElementById('cap_cuota_max').textContent = formatMoney(cuotaDisponible);
    document.getElementById('cap_monto_max').textContent = formatMoney(montoMaximo);
    
    let mensaje = '';
    if (cuotaDisponible <= 0) {
        mensaje = '<i class="fas fa-exclamation-triangle me-1"></i>Tus deudas actuales ya superan el límite recomendado. Considera pagar algunas antes de adquirir nuevos créditos.';
    } else {
        const porcentajeUsado = ((deudasActuales / ingreso) * 100).toFixed(1);
        mensaje = `<i class="fas fa-info-circle me-1"></i>Actualmente destinas el ${porcentajeUsado}% de tus ingresos a deudas. Podrías tomar un crédito con cuota máxima de ${formatMoney(cuotaDisponible)}.`;
    }
    document.getElementById('cap_mensaje').innerHTML = mensaje;
    document.getElementById('cap_resultado').style.display = 'block';
}

// Simulador de Inflación
function calcularInflacion() {
    const monto = parseFloat(document.getElementById('inf_monto').value) || 0;
    const tasaInflacion = parseFloat(document.getElementById('inf_tasa').value) / 100;
    const anios = parseInt(document.getElementById('inf_anios').value) || 1;
    
    // Valor real: cuánto valdrá tu dinero en términos de poder adquisitivo
    const valorReal = monto / Math.pow(1 + tasaInflacion, anios);
    const perdida = monto - valorReal;
    
    // Cuánto necesitarás para mantener el poder adquisitivo
    const necesario = monto * Math.pow(1 + tasaInflacion, anios);
    
    document.getElementById('inf_anios_texto').textContent = anios;
    document.getElementById('inf_valor_real').textContent = formatMoney(valorReal);
    document.getElementById('inf_perdida').textContent = '-' + formatMoney(perdida);
    document.getElementById('inf_necesario').textContent = formatMoney(necesario);
    document.getElementById('inf_hoy').textContent = formatMoney(monto);
    document.getElementById('inf_resultado').style.display = 'block';
}

// Simulador Regla 50/30/20
let reglaChartInstance = null;
function calcularRegla() {
    const ingreso = parseFloat(document.getElementById('regla_ingreso').value) || 0;
    
    const necesidades = ingreso * 0.5;
    const deseos = ingreso * 0.3;
    const ahorro = ingreso * 0.2;
    
    document.getElementById('regla_50').textContent = formatMoney(necesidades);
    document.getElementById('regla_30').textContent = formatMoney(deseos);
    document.getElementById('regla_20').textContent = formatMoney(ahorro);
    document.getElementById('regla_resultado').style.display = 'block';
    
    // Gráfica
    const ctx = document.getElementById('reglaChart').getContext('2d');
    if (reglaChartInstance) reglaChartInstance.destroy();
    
    const colors = getChartColors();
    
    reglaChartInstance = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Necesidades (50%)', 'Deseos (30%)', 'Ahorro (20%)'],
            datasets: [{
                data: [necesidades, deseos, ahorro],
                backgroundColor: ['#0d6efd', '#ffc107', '#198754']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'right', labels: { color: colors.textColor } } }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
