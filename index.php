<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$dbConectada = false;
$dbError = '';

try {
    $pdo = obtenerConexion();
    $pdo->query('SELECT 1');
    $dbConectada = true;
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics Musical — Sistema de Estadísticas BDDR (PHP + MySQL)</title>
  <link rel="stylesheet" href="css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="app-container">
    <!-- Barra Superior y Filtros -->
    <header class="topbar">
      <div>
        <div class="title-row">
          <h1>Estadísticas Musicales</h1>
          <span class="status-pill <?= $dbConectada ? 'status-ok' : 'status-error' ?>">
            <?= $dbConectada ? 'MySQL Conectado' : 'Sin conexión a MySQL' ?>
          </span>
        </div>
        <p class="subtitle">Análisis relacional con PHP + MySQL de catálogo, usuarios e historial de reproducciones</p>
      </div>
      <div class="filters">
        <div class="filter-group">
          <label for="filterUser">Usuario</label>
          <select id="filterUser">
            <option value="all">Todos los usuarios</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filterYear">Año</label>
          <select id="filterYear">
            <option value="all">Todos los años</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filterQuarter">Periodo</label>
          <select id="filterQuarter">
            <option value="all">Todo el año</option>
            <option value="Q1">Trimestre 1 (Ene - Mar)</option>
            <option value="Q2">Trimestre 2 (Abr - Jun)</option>
            <option value="Q3">Trimestre 3 (Jul - Sep)</option>
            <option value="Q4">Trimestre 4 (Oct - Dic)</option>
          </select>
        </div>
      </div>
    </header>

    <?php if (!$dbConectada): ?>
      <section class="alert-banner alert-error" role="alert">
        <strong>No se pudo conectar a MySQL:</strong>
        <?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?>
        <br>
        Importa <code>sql/database.sql</code> en tu servidor MySQL y revisa los datos de acceso en <code>config/db.php</code>.
      </section>
    <?php endif; ?>

    <div id="statusFeedback" class="alert-banner hidden" role="status"></div>

    <!-- A. Estadísticas Generales (6 Indicadores KPI) -->
    <section class="kpi-grid" aria-label="Indicadores Generales">
      <article class="card kpi-card">
        <span class="kpi-label">Canciones Registradas</span>
        <strong class="kpi-value" id="kpiTotalCanciones">0</strong>
        <span class="kpi-meta" id="kpiPromedioDuracion">Prom: 0:00 min</span>
      </article>
      <article class="card kpi-card">
        <span class="kpi-label">Artistas / Géneros</span>
        <strong class="kpi-value" id="kpiArtistasGeneros">0 / 0</strong>
        <span class="kpi-meta" id="kpiTopPais">País líder: —</span>
      </article>
      <article class="card kpi-card">
        <span class="kpi-label">Total Reproducciones</span>
        <strong class="kpi-value" id="kpiTotalReproducciones">0</strong>
        <span class="kpi-meta" id="kpiMesPico">Mes pico: —</span>
      </article>
      <article class="card kpi-card">
        <span class="kpi-label">Tiempo Total Escuchado</span>
        <strong class="kpi-value" id="kpiTiempoTotal">0h 0m</strong>
        <span class="kpi-meta" id="kpiDiaPico">Día más activo: —</span>
      </article>
      <article class="card kpi-card">
        <span class="kpi-label">Canción #1 del Periodo</span>
        <strong class="kpi-value text-truncate" id="kpiTopCancion">—</strong>
        <span class="kpi-meta" id="kpiTopCancionDetalle">0 reproducciones</span>
      </article>
      <article class="card kpi-card">
        <span class="kpi-label">Género Líder</span>
        <strong class="kpi-value text-truncate" id="kpiTopGenero">—</strong>
        <span class="kpi-meta" id="kpiTopGeneroDetalle">0 min acumulados</span>
      </article>
    </section>

    <!-- Visualizaciones Estadísticas (4 Gráficos) -->
    <section class="charts-grid">
      <article class="card chart-card span-2">
        <div class="card-header">
          <h2>Reproducciones por Mes</h2>
          <span class="badge">Historial Temporal</span>
        </div>
        <div class="canvas-wrapper">
          <canvas id="chartMensual"></canvas>
        </div>
      </article>

      <article class="card chart-card">
        <div class="card-header">
          <h2>Tiempo por Género Musical</h2>
          <span class="badge">Minutos Escuchados</span>
        </div>
        <div class="canvas-wrapper">
          <canvas id="chartGeneros"></canvas>
        </div>
      </article>

      <article class="card chart-card">
        <div class="card-header">
          <h2>Actividad por Día de la Semana</h2>
          <span class="badge">Frecuencia</span>
        </div>
        <div class="canvas-wrapper">
          <canvas id="chartDias"></canvas>
        </div>
      </article>

      <article class="card chart-card span-2">
        <div class="card-header">
          <h2>Países de Origen Más Escuchados</h2>
          <span class="badge">Reproducciones por País</span>
        </div>
        <div class="canvas-wrapper">
          <canvas id="chartPaises"></canvas>
        </div>
      </article>
    </section>

    <!-- Rankings Detallados (2 Tablas) -->
    <section class="tables-grid">
      <article class="card table-card">
        <div class="card-header">
          <div>
            <h2>Ranking de Canciones</h2>
            <p class="card-subtitle" id="subtituloTablaCanciones">Top 10 más escuchadas y tiempo acumulado</p>
          </div>
          <div class="toggle-group">
            <button type="button" class="btn-toggle active" data-mode="top">Más escuchadas</button>
            <button type="button" class="btn-toggle" data-mode="bottom">Menos reproducidas</button>
          </div>
        </div>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Canción</th>
                <th>Artista</th>
                <th>Género</th>
                <th class="num">Reprod.</th>
                <th class="num">Tiempo</th>
              </tr>
            </thead>
            <tbody id="tbodyCanciones"></tbody>
          </table>
        </div>
      </article>

      <article class="card table-card">
        <div class="card-header">
          <div>
            <h2>Ranking de Artistas y Catálogo</h2>
            <p class="card-subtitle">Top artistas escuchados y canciones registradas</p>
          </div>
        </div>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Artista</th>
                <th>País</th>
                <th class="num">Canciones</th>
                <th class="num">Reprod.</th>
                <th class="num">Tiempo</th>
              </tr>
            </thead>
            <tbody id="tbodyArtistas"></tbody>
          </table>
        </div>
      </article>
    </section>

    <!-- F. Usuarios y Simulador de Reproducción (Demo) -->
    <section class="card">
      <div class="card-header usuarios-header">
        <div>
          <h2>Usuarios Registrados y Actividad</h2>
          <p class="card-subtitle">Comparativa del periodo seleccionado · Haz clic en un usuario para filtrar</p>
        </div>
        <form id="formDemoReproduccion" class="demo-form">
          <select id="demoUsuario" aria-label="Usuario para registrar reproducción" required></select>
          <select id="demoCancion" aria-label="Canción para registrar reproducción" required></select>
          <button type="submit" class="btn-primary" id="btnRegistrarPlay">+ Registrar reproducción</button>
        </form>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Usuario</th>
              <th>Correo</th>
              <th>País</th>
              <th class="num">Canciones distintas</th>
              <th class="num">Reprod.</th>
              <th class="num">Tiempo</th>
            </tr>
          </thead>
          <tbody id="tbodyUsuarios"></tbody>
        </table>
      </div>
    </section>
  </div>

  <script type="module" src="js/app.js"></script>
</body>
</html>
