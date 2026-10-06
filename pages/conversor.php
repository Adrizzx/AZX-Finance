<?php
/**
 * AZX-Finance - Conversor de Monedas
 */
$pageTitle = 'Conversor';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();

// Obtener monedas de la base de datos
$stmt = $db->query("SELECT * FROM monedas WHERE activa = 1 ORDER BY codigo");
$monedas = $stmt->fetchAll();

// Crear mapa de tasas
$tasasMap = [];
foreach ($monedas as $moneda) {
    $tasasMap[$moneda['codigo']] = [
        'nombre' => $moneda['nombre'],
        'simbolo' => $moneda['simbolo'],
        'tasa' => $moneda['tasa_cambio_usd']
    ];
}
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><i class="fas fa-exchange-alt text-warning me-2"></i>Conversor de Monedas</h1>
        <p>Convierte entre diferentes divisas</p>
    </div>
</div>

<div class="row g-4">
    <!-- Conversor Principal -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning bg-opacity-10 border-0">
                <h5 class="card-title mb-0 text-warning">
                    <i class="fas fa-calculator me-2"></i>Convertir
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-12">
                        <label class="form-label fw-bold">Cantidad</label>
                        <input type="number" class="form-control form-control-lg" id="cantidad" 
                               value="1000" step="0.01" oninput="convertir()">
                    </div>
                    
                    <div class="col-md-5">
                        <label class="form-label fw-bold">De</label>
                        <select class="form-select form-select-lg" id="monedaOrigen" onchange="convertir()">
                            <?php foreach ($monedas as $m): ?>
                            <option value="<?= $m['codigo'] ?>" <?= $m['codigo'] == 'COP' ? 'selected' : '' ?>>
                                <?= $m['simbolo'] ?> <?= $m['codigo'] ?> - <?= $m['nombre'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end justify-content-center">
                        <button class="btn btn-soft-warning btn-lg" onclick="intercambiar()">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                    </div>
                    
                    <div class="col-md-5">
                        <label class="form-label fw-bold">A</label>
                        <select class="form-select form-select-lg" id="monedaDestino" onchange="convertir()">
                            <?php foreach ($monedas as $m): ?>
                            <option value="<?= $m['codigo'] ?>" <?= $m['codigo'] == 'USD' ? 'selected' : '' ?>>
                                <?= $m['simbolo'] ?> <?= $m['codigo'] ?> - <?= $m['nombre'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <div class="bg-light rounded-3 p-4 text-center">
                            <small class="text-muted d-block mb-2">Resultado</small>
                            <h2 class="mb-0 text-success" id="resultado">$ 0.00</h2>
                            <small class="text-muted" id="tasaCambio"></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabla de Tasas -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Tasas de Cambio (Base: USD)
                </h5>
                <small class="text-muted">Última actualización: <?= date('d/m/Y') ?></small>
            </div>
            <div class="card-body p-0">
                <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>Moneda</th>
                                <th>Código</th>
                                <th class="text-end">1 USD =</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monedas as $m): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?= $m['simbolo'] ?></span>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= $m['codigo'] ?></span></td>
                                <td class="text-end fw-bold">
                                    <?= $m['simbolo'] ?> <?= number_format($m['tasa_cambio_usd'], 2, ',', '.') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Conversiones Rápidas -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt me-2 text-warning"></i>Conversiones Rápidas
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4" id="conversionesRapidas">
                    <!-- Se llenan con JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calculadora de Viaje -->
<div class="row g-4 mt-2">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-info bg-opacity-10 border-0">
                <h5 class="card-title mb-0 text-info">
                    <i class="fas fa-plane me-2"></i>Presupuesto de Viaje
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Presupuesto en <?= APP_CURRENCY ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= APP_CURRENCY_SYMBOL ?></span>
                            <input type="number" class="form-control" id="presupuestoViaje" 
                                   value="5000000" oninput="calcularViaje()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Moneda del Destino</label>
                        <select class="form-select" id="monedaViaje" onchange="calcularViaje()">
                            <?php foreach ($monedas as $m): ?>
                            <option value="<?= $m['codigo'] ?>" <?= $m['codigo'] == 'USD' ? 'selected' : '' ?>>
                                <?= $m['codigo'] ?> - <?= $m['nombre'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Días del Viaje</label>
                        <input type="number" class="form-control" id="diasViaje" 
                               value="7" oninput="calcularViaje()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Margen de Seguridad (%)</label>
                        <input type="number" class="form-control" id="margenViaje" 
                               value="10" oninput="calcularViaje()">
                    </div>
                </div>
                
                <div id="resultadoViaje" class="mt-4">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Total en Destino</small>
                                <h5 class="mb-0 text-primary" id="totalDestino"></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Por Día</small>
                                <h5 class="mb-0 text-info" id="porDia"></h5>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-light rounded p-3">
                                <small class="text-muted d-block">Recomendado Llevar</small>
                                <h5 class="mb-0 text-success" id="recomendado"></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historial de Conversiones -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Historial de Conversiones
                </h5>
                <button class="btn btn-sm btn-soft-danger" onclick="limpiarHistorial()">
                    <i class="fas fa-trash"></i> Limpiar
                </button>
            </div>
            <div class="card-body">
                <div id="historialConversiones" class="list-group list-group-flush" 
                     style="max-height: 250px; overflow-y: auto;">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-clock fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">Las conversiones aparecerán aquí</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const tasas = <?= json_encode($tasasMap) ?>;
let historial = JSON.parse(localStorage.getItem('historialConversiones') || '[]');

document.addEventListener('DOMContentLoaded', function() {
    convertir();
    mostrarConversionesRapidas();
    calcularViaje();
    mostrarHistorial();
});

function convertir() {
    const cantidad = parseFloat(document.getElementById('cantidad').value) || 0;
    const origen = document.getElementById('monedaOrigen').value;
    const destino = document.getElementById('monedaDestino').value;
    
    // Convertir a USD primero, luego a destino
    const cantidadUSD = cantidad / tasas[origen].tasa;
    const resultado = cantidadUSD * tasas[destino].tasa;
    
    document.getElementById('resultado').textContent = 
        tasas[destino].simbolo + ' ' + resultado.toLocaleString('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    
    const tasa = tasas[destino].tasa / tasas[origen].tasa;
    document.getElementById('tasaCambio').textContent = 
        `1 ${origen} = ${tasa.toFixed(4)} ${destino}`;
    
    // Guardar en historial
    if (cantidad > 0) {
        agregarHistorial(cantidad, origen, resultado, destino);
    }
}

function intercambiar() {
    const origen = document.getElementById('monedaOrigen');
    const destino = document.getElementById('monedaDestino');
    const temp = origen.value;
    origen.value = destino.value;
    destino.value = temp;
    convertir();
}

function mostrarConversionesRapidas() {
    const container = document.getElementById('conversionesRapidas');
    const monedasPopulares = ['USD', 'EUR', 'COP', 'MXN'];
    const cantidad = 1;
    
    let html = '';
    monedasPopulares.forEach(moneda => {
        html += `<div class="col-md-3"><div class="border rounded p-3 text-center h-100">
            <h6 class="mb-3">${tasas[moneda].simbolo} 1 ${moneda}</h6>`;
        
        monedasPopulares.filter(m => m !== moneda).forEach(otra => {
            const tasa = tasas[otra].tasa / tasas[moneda].tasa;
            html += `<div class="d-flex justify-content-between mb-1">
                <small>${otra}</small>
                <small class="fw-bold">${tasas[otra].simbolo} ${tasa.toFixed(4)}</small>
            </div>`;
        });
        
        html += '</div></div>';
    });
    
    container.innerHTML = html;
}

function calcularViaje() {
    const presupuesto = parseFloat(document.getElementById('presupuestoViaje').value) || 0;
    const moneda = document.getElementById('monedaViaje').value;
    const dias = parseInt(document.getElementById('diasViaje').value) || 1;
    const margen = parseFloat(document.getElementById('margenViaje').value) || 0;
    
    // Convertir de COP a la moneda destino
    const presupuestoUSD = presupuesto / tasas['<?= APP_CURRENCY ?>'].tasa;
    const totalDestino = presupuestoUSD * tasas[moneda].tasa;
    const porDia = totalDestino / dias;
    const recomendado = totalDestino * (1 + margen / 100);
    
    document.getElementById('totalDestino').textContent = 
        tasas[moneda].simbolo + ' ' + totalDestino.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('porDia').textContent = 
        tasas[moneda].simbolo + ' ' + porDia.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('recomendado').textContent = 
        tasas[moneda].simbolo + ' ' + recomendado.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function agregarHistorial(cantidad, origen, resultado, destino) {
    const conversion = {
        fecha: new Date().toLocaleString('es-CO'),
        cantidad: cantidad,
        origen: origen,
        resultado: resultado,
        destino: destino
    };
    
    // Evitar duplicados recientes
    if (historial.length > 0) {
        const ultimo = historial[0];
        if (ultimo.cantidad === cantidad && ultimo.origen === origen && ultimo.destino === destino) {
            return;
        }
    }
    
    historial.unshift(conversion);
    historial = historial.slice(0, 20); // Mantener solo 20
    localStorage.setItem('historialConversiones', JSON.stringify(historial));
    mostrarHistorial();
}

function mostrarHistorial() {
    const container = document.getElementById('historialConversiones');
    
    if (historial.length === 0) {
        container.innerHTML = `<div class="text-center text-muted py-4">
            <i class="fas fa-clock fa-2x mb-2 opacity-50"></i>
            <p class="mb-0">Las conversiones aparecerán aquí</p>
        </div>`;
        return;
    }
    
    container.innerHTML = historial.map(h => `
        <div class="list-group-item px-0">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-bold">${tasas[h.origen].simbolo} ${h.cantidad.toLocaleString('es-CO')}</span>
                    <span class="text-muted mx-2">→</span>
                    <span class="text-success fw-bold">${tasas[h.destino].simbolo} ${h.resultado.toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                </div>
                <small class="text-muted">${h.fecha}</small>
            </div>
        </div>
    `).join('');
}

function limpiarHistorial() {
    historial = [];
    localStorage.removeItem('historialConversiones');
    mostrarHistorial();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
