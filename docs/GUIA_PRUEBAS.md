# AZX-Finance - Guía de Pruebas

## 🔐 Sistema de Perfiles (Estilo Netflix)

### Login (`login.php`)
- Muestra los **5 perfiles más recientes** (ordenados por `ultimo_acceso`)
- Al hacer clic en un perfil, aparece modal para ingresar contraseña
- Botón "Ver todos" si hay más de 5 perfiles → lleva a `todos-perfiles.php`
- Botón "Añadir perfil" → lleva a `registro.php`

**Probar:**
1. Crear perfil en `registro.php` con nombre, email, contraseña, avatar y color
2. Intentar login con contraseña correcta → debe redirigir a Dashboard
3. Intentar login con contraseña incorrecta → debe mostrar error
4. Verificar que el perfil recién usado aparece primero al volver a `login.php`

**Usuarios de prueba en la BD:**
- `usuario1@azxfinance.com` / contraseña: `1234`
- `usuario2@azxfinance.com` / contraseña: `1234`
- `usuario3@azxfinance.com` / contraseña: `1234`

---

## 📊 Dashboard (`index.php`)

Muestra resumen financiero del usuario logueado:

| Sección | Descripción |
|---------|-------------|
| Total Ingresos (mes) | Suma de todos los ingresos del mes actual |
| Total Gastos (mes) | Suma de todos los gastos del mes actual |
| Balance | Ingresos - Gastos |
| Total Ahorros | Suma de saldos de todos los bolsillos activos |
| Total Deudas | Suma de (monto_total - monto_pagado) de deudas activas |
| Gastos por Categoría | Gráfico de torta con gastos del mes |
| Metas Activas | 4 metas con mayor prioridad y su progreso |
| Últimas Transacciones | 10 movimientos más recientes (ingresos + gastos) |

**Probar:**
1. Agregar ingresos y verificar que el total se actualiza
2. Agregar gastos y verificar balance
3. Verificar que cada usuario ve solo sus propios datos

---

## 💰 Ingresos (`pages/ingresos.php`)

### Funcionalidades:
- **Listar ingresos** del mes actual (filtrable por mes/año)
- **Agregar ingreso**: descripción, monto, categoría, fecha
- **Editar ingreso**: clic en botón editar de cada fila
- **Eliminar ingreso**: con confirmación

### Categorías de Ingresos:
- Salario, Freelance, Inversiones, Ventas, Regalos, Otros

**Probar:**
1. Agregar ingreso con categoría "Salario"
2. Cambiar mes/año y verificar filtrado
3. Editar un ingreso existente
4. Eliminar y verificar que desaparece

---

## 💸 Gastos (`pages/gastos.php`)

### Funcionalidades:
- **Listar gastos** del mes actual
- **Agregar gasto**: descripción, monto, categoría, fecha
- **Editar/Eliminar** gastos

### Categorías de Gastos:
- Alimentación, Transporte, Vivienda, Servicios, Entretenimiento, Salud, Educación, Ropa, Tecnología, Otros

**Probar:**
1. Agregar gasto en categoría "Alimentación"
2. Verificar que aparece en Dashboard
3. Verificar gráfico de gastos por categoría

---

## 🎯 Metas de Ahorro (`pages/metas.php`)

### Funcionalidades:
- **Crear meta**: nombre, monto objetivo, fecha límite, prioridad, color, icono
- **Ver progreso**: barra de progreso con porcentaje
- **Agregar aporte**: incrementa `monto_actual`
- **Editar/Eliminar** metas
- Estados: `activa`, `completada`, `cancelada`

**Probar:**
1. Crear meta "Vacaciones" con objetivo $1,000,000
2. Agregar aporte de $200,000
3. Verificar que la barra muestra 20%
4. Verificar días restantes calculados correctamente
5. Completar meta al 100% → debe cambiar estado

---

## 🐷 Bolsillos de Ahorro (`pages/ahorros.php`)

### Funcionalidades:
- **Crear bolsillo**: nombre, descripción, icono, color
- **Depósito**: agregar dinero al bolsillo
- **Retiro**: sacar dinero del bolsillo
- **Transferencia**: mover dinero entre bolsillos
- **Ver movimientos**: historial de cada bolsillo

**Probar:**
1. Crear bolsillo "Emergencias"
2. Hacer depósito de $500,000
3. Crear segundo bolsillo "Viajes"
4. Transferir $100,000 de Emergencias a Viajes
5. Verificar saldos actualizados
6. Ver historial de movimientos

---

## 📅 Presupuesto Mensual (`pages/presupuesto.php`)

### Funcionalidades:
- **Establecer presupuesto** por categoría de gasto
- **Ver consumo** vs presupuesto (barra de progreso)
- **Alertas** cuando se supera el 80% o 100%
- Navegación por mes/año

**Probar:**
1. Establecer presupuesto de $200,000 para "Alimentación"
2. Agregar gasto de $150,000 en Alimentación
3. Verificar barra al 75%
4. Agregar otro gasto de $100,000 → debe mostrar alerta de exceso

---

## 💳 Deudas (`pages/deudas.php`)

### Funcionalidades:
- **Registrar deuda**: nombre, monto total, tasa interés, cuota mensual, fecha inicio/vencimiento, tipo, acreedor
- **Registrar pago**: incrementa `monto_pagado`
- **Ver progreso** de pago
- Estados: `activa`, `pagada`, `atrasada`, `en_negociacion`
- Tipos: tarjeta_credito, prestamo_personal, hipoteca, vehiculo, educativo, otro

**Probar:**
1. Crear deuda "Tarjeta Visa" por $2,000,000
2. Registrar pago de $500,000
3. Verificar porcentaje pagado (25%)
4. Pagar completamente → estado debe cambiar a "pagada"

---

## 📈 Inversiones (`pages/inversiones.php`)

### Funcionalidades:
- **Registrar inversión**: nombre, tipo, monto invertido, valor actual, tasa rendimiento, fecha inicio/vencimiento
- **Actualizar valor**: modificar valor actual
- **Calcular rendimiento**: (valor_actual - monto_invertido) / monto_invertido * 100
- Tipos: CDT, acciones, fondos, cripto, bienes_raices, otro
- Estados: activa, cerrada

**Probar:**
1. Crear inversión "CDT Bancolombia" de $5,000,000
2. Actualizar valor actual a $5,200,000
3. Verificar rendimiento mostrado (4%)
4. Cerrar inversión

---

## 🧮 Simuladores (`pages/simuladores.php`)

### 6 Simuladores disponibles:

| Simulador | Función |
|-----------|---------|
| **Interés Compuesto** | Proyecta crecimiento: capital inicial + aportes mensuales × tasa × años |
| **Ahorro para Meta** | Calcula cuánto ahorrar mensualmente para alcanzar objetivo |
| **Simulador Préstamo** | Calcula cuota mensual según monto, tasa, plazo (Francés o Alemán) |
| **Capacidad Endeudamiento** | Cuánto crédito puedes tomar según ingresos y deudas |
| **Impacto Inflación** | Cuánto pierde valor tu dinero en X años |
| **Regla 50/30/20** | Distribución ideal: 50% necesidades, 30% deseos, 20% ahorro |

**Probar cada uno:**
1. Interés Compuesto: $1,000,000 inicial + $200,000/mes × 10% × 10 años
2. Ahorro Meta: ¿Cuánto mensual para $30,000,000 en 24 meses?
3. Préstamo: $20,000,000 al 24% anual en 36 meses
4. Capacidad: Con $3,500,000 ingreso y $500,000 deudas
5. Inflación: $10,000,000 con 8% inflación en 5 años
6. Regla 50/30/20: $4,000,000 de ingreso

---

## 📊 Reportes (`pages/reportes.php`)

### Funcionalidades:
- **Gráficos de ingresos vs gastos** (últimos 6-12 meses)
- **Top categorías** de gastos
- **Evolución del patrimonio**
- Filtros por rango de fechas

**Probar:**
1. Agregar varios ingresos y gastos en diferentes meses
2. Verificar que los gráficos muestran datos correctos
3. Cambiar rango de fechas

---

## 💱 Conversor de Monedas (`pages/conversor.php`)

### Funcionalidades:
- Conversión entre monedas (USD, EUR, COP, MXN, etc.)
- Tasas de cambio (pueden ser fijas o de API)

**Probar:**
1. Convertir 100 USD a COP
2. Verificar cálculo correcto

---

## 👤 Perfil de Usuario (`pages/perfil.php`)

### Funcionalidades:
- **Editar nombre y email**
- **Cambiar avatar** (subir imagen)
- **Cambiar color** del perfil
- **Cambiar contraseña** (requiere contraseña actual)
- Ver estadísticas del usuario

**Probar:**
1. Cambiar nombre del perfil
2. Subir nueva foto de avatar
3. Cambiar color del perfil
4. Cambiar contraseña y verificar nuevo login

---

## 🚪 Cerrar Sesión (`logout.php`)

- Destruye la sesión
- Redirige a `login.php`

**Probar:**
1. Hacer logout
2. Intentar acceder a `index.php` directamente → debe redirigir a login

---

## ⚠️ Puntos a Verificar (Posibles Incoherencias)

1. [ ] Cada usuario solo ve sus propios datos
2. [ ] Los totales del Dashboard coinciden con las sumas de las páginas individuales
3. [ ] Al eliminar un bolsillo, los movimientos se eliminan (CASCADE)
4. [ ] Las metas completadas no aparecen en "activas"
5. [ ] El presupuesto muestra correctamente el mes seleccionado
6. [ ] Los porcentajes de deudas/metas se calculan correctamente
7. [ ] Las fechas se muestran en formato correcto
8. [ ] Los montos usan el símbolo de moneda configurado

---

## 🔧 Configuración (`config/config.php`)

```php
APP_NAME = 'AZX-Finance'
APP_CURRENCY = 'COP'
APP_CURRENCY_SYMBOL = '$'
MAX_PROFILES_DISPLAY = 5
```

Para cambiar moneda, editar estas constantes.

---

## 📁 Estructura de Archivos

```
PRY_AZXFinance/
├── config/
│   ├── config.php      # Configuración y funciones auth
│   └── database.php    # Conexión PDO a MySQL
├── database/
│   └── azx_finance.sql # Script para crear la BD
├── includes/
│   ├── header.php      # Layout con sidebar
│   └── footer.php      # Cierre HTML y scripts
├── pages/
│   ├── ingresos.php
│   ├── gastos.php
│   ├── metas.php
│   ├── ahorros.php
│   ├── presupuesto.php
│   ├── deudas.php
│   ├── inversiones.php
│   ├── simuladores.php
│   ├── reportes.php
│   ├── conversor.php
│   └── perfil.php
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── uploads/avatars/    # Fotos de perfil
├── index.php           # Dashboard
├── login.php           # Selector de perfiles
├── registro.php        # Crear nuevo perfil
├── logout.php          # Cerrar sesión
└── todos-perfiles.php  # Ver todos los perfiles
```
