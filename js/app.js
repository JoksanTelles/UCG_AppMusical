import { ChartsManager } from './chartsManager.js';

const charts = new ChartsManager();
let modoTablaCanciones = 'top'; // 'top' | 'bottom'
let ultimasStatsCanciones = { masEscuchadas: [], menosReproducidas: [] };
let catalogosInicializados = false;

// Utilidades de formato y seguridad
function formatearMinSeg(segundos) {
  const total = Number(segundos) || 0;
  const min = Math.floor(total / 60);
  const seg = total % 60;
  return `${min}:${seg.toString().padStart(2, '0')}`;
}

function formatearHorasMin(segundos) {
  const total = Number(segundos) || 0;
  const horas = Math.floor(total / 3600);
  const min = Math.floor((total % 3600) / 60);
  return `${horas}h ${min}m`;
}

function escaparHtml(texto) {
  return String(texto ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function mostrarFeedback(mensaje, tipo = 'ok') {
  const banner = document.getElementById('statusFeedback');
  banner.textContent = mensaje;
  banner.className = `alert-banner ${tipo === 'error' ? 'alert-error' : 'alert-ok'}`;
  banner.classList.remove('hidden');

  if (tipo === 'ok') {
    setTimeout(() => {
      banner.classList.add('hidden');
    }, 4000);
  }
}

async function inicializarApp() {
  configurarEventos();
  await actualizarDashboard();
}

function sincronizarSelectores(catalogos) {
  const selectAnio = document.getElementById('filterYear');
  const anioActual = selectAnio.value;
  selectAnio.innerHTML = '<option value="all">Todos los años</option>';
  catalogos.anios.forEach(anio => {
    const option = document.createElement('option');
    option.value = String(anio);
    option.textContent = String(anio);
    selectAnio.appendChild(option);
  });
  if ([...selectAnio.options].some(o => o.value === anioActual)) {
    selectAnio.value = anioActual;
  }

  if (catalogosInicializados) return;

  const selectUser = document.getElementById('filterUser');
  const demoUser = document.getElementById('demoUsuario');
  const demoSong = document.getElementById('demoCancion');

  catalogos.usuarios.forEach(u => {
    const optFiltro = document.createElement('option');
    optFiltro.value = String(u.id);
    optFiltro.textContent = u.nombre;
    selectUser.appendChild(optFiltro);

    const optDemo = document.createElement('option');
    optDemo.value = String(u.id);
    optDemo.textContent = u.nombre;
    demoUser.appendChild(optDemo);
  });

  catalogos.canciones.forEach(c => {
    const optSong = document.createElement('option');
    optSong.value = String(c.id);
    optSong.textContent = `${c.artista_nombre} — ${c.titulo} (${formatearMinSeg(c.duracion_seg)})`;
    demoSong.appendChild(optSong);
  });

  catalogosInicializados = true;
}

function configurarEventos() {
  document.getElementById('filterUser').addEventListener('change', actualizarDashboard);
  document.getElementById('filterYear').addEventListener('change', actualizarDashboard);
  document.getElementById('filterQuarter').addEventListener('change', actualizarDashboard);

  document.querySelectorAll('.btn-toggle').forEach(btn => {
    btn.addEventListener('click', e => {
      document.querySelectorAll('.btn-toggle').forEach(b => b.classList.remove('active'));
      e.currentTarget.classList.add('active');
      modoTablaCanciones = e.currentTarget.dataset.mode;
      renderizarTablaCanciones();
    });
  });

  document.getElementById('formDemoReproduccion').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btnRegistrarPlay');
    const usuarioId = Number(document.getElementById('demoUsuario').value);
    const cancionId = Number(document.getElementById('demoCancion').value);

    if (!usuarioId || !cancionId) return;

    btn.disabled = true;
    btn.textContent = 'Guardando...';

    try {
      const resp = await fetch('api/reproduccion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario_id: usuarioId, cancion_id: cancionId })
      });
      const resultado = await resp.json();
      if (!resp.ok || !resultado.ok) {
        throw new Error(resultado.error || 'Error al guardar la reproducción');
      }

      mostrarFeedback(resultado.mensaje, 'ok');
      await actualizarDashboard();
    } catch (error) {
      mostrarFeedback(error.message, 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = '+ Registrar reproducción';
    }
  });
}

async function actualizarDashboard() {
  const usuarioId = document.getElementById('filterUser').value;
  const anio = document.getElementById('filterYear').value;
  const trimestre = document.getElementById('filterQuarter').value;

  const params = new URLSearchParams({
    usuario_id: usuarioId,
    anio,
    trimestre
  });

  try {
    const respuesta = await fetch(`api/estadisticas.php?${params.toString()}`);
    const data = await respuesta.json();

    if (!respuesta.ok || !data.ok) {
      throw new Error(data.error || 'No se pudieron obtener las estadísticas desde PHP/MySQL');
    }

    sincronizarSelectores(data.catalogos);

    const generales = data.generales;
    ultimasStatsCanciones = data.canciones;
    const artistasStats = data.artistas;
    const generosStats = data.generos;
    const temporalesStats = data.temporales;

    // Renderizar KPIs
    document.getElementById('kpiTotalCanciones').textContent = generales.totalCanciones;
    document.getElementById('kpiPromedioDuracion').textContent =
      `Prom: ${formatearMinSeg(generales.promedioDuracionSeg)} · Mín/Máx: ${formatearMinSeg(generales.duracionMinSeg)} / ${formatearMinSeg(generales.duracionMaxSeg)}`;

    document.getElementById('kpiArtistasGeneros').textContent =
      `${generales.totalArtistas} / ${generales.totalGeneros}`;
    const paisLider = artistasStats.rankingPaises[0];
    document.getElementById('kpiTopPais').textContent =
      paisLider && paisLider.reproducciones > 0 ? `País líder: ${paisLider.pais}` : 'País líder: —';

    document.getElementById('kpiTotalReproducciones').textContent = generales.totalReproducciones;
    document.getElementById('kpiMesPico').textContent = `Mes pico: ${temporalesStats.mesPico}`;

    document.getElementById('kpiTiempoTotal').textContent = formatearHorasMin(generales.tiempoTotalSeg);
    document.getElementById('kpiDiaPico').textContent = `Día más activo: ${temporalesStats.diaPico}`;

    const topCancion = ultimasStatsCanciones.masEscuchadas[0];
    document.getElementById('kpiTopCancion').textContent = topCancion ? topCancion.titulo : '—';
    document.getElementById('kpiTopCancionDetalle').textContent = topCancion
      ? `${topCancion.reproducciones} reprod. (${topCancion.artista_nombre})`
      : '0 reproducciones';

    const topGenero = generosStats[0];
    document.getElementById('kpiTopGenero').textContent =
      topGenero && topGenero.tiempo_escuchado_seg > 0 ? topGenero.nombre : '—';
    document.getElementById('kpiTopGeneroDetalle').textContent = topGenero
      ? `${Math.round(topGenero.tiempo_escuchado_seg / 60)} min acumulados`
      : '0 min acumulados';

    // Renderizar Gráficos
    charts.renderMensual(temporalesStats.etiquetasMeses, temporalesStats.valoresMeses);
    charts.renderGeneros(generosStats);
    charts.renderDias(temporalesStats.etiquetasDias, temporalesStats.valoresDias);
    charts.renderPaises(artistasStats.rankingPaises);

    // Renderizar Tablas
    renderizarTablaCanciones();
    renderizarTablaArtistas(artistasStats.rankingArtistas);
    renderizarTablaUsuarios(data.usuarios, usuarioId);
  } catch (error) {
    console.error('Error consultando estadísticas:', error);
    mostrarFeedback(error.message, 'error');
  }
}

function renderizarTablaCanciones() {
  const tbody = document.getElementById('tbodyCanciones');
  const subtitulo = document.getElementById('subtituloTablaCanciones');
  const lista = modoTablaCanciones === 'top'
    ? ultimasStatsCanciones.masEscuchadas
    : ultimasStatsCanciones.menosReproducidas;

  subtitulo.textContent = modoTablaCanciones === 'top'
    ? 'Top 10 canciones más escuchadas en el periodo'
    : 'Top 10 canciones con menor actividad (incluye 0 reproducciones)';

  if (!lista.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Sin reproducciones para los filtros seleccionados.</td></tr>';
    return;
  }

  tbody.innerHTML = lista.map((c, idx) => `
    <tr>
      <td>${idx + 1}</td>
      <td><strong>${escaparHtml(c.titulo)}</strong></td>
      <td>${escaparHtml(c.artista_nombre)}</td>
      <td>${escaparHtml(c.genero_nombre)}</td>
      <td class="num">${c.reproducciones}</td>
      <td class="num">${formatearMinSeg(c.tiempo_acumulado_seg)}</td>
    </tr>
  `).join('');
}

function renderizarTablaArtistas(rankingArtistas) {
  const tbody = document.getElementById('tbodyArtistas');
  tbody.innerHTML = rankingArtistas.map((art, idx) => `
    <tr>
      <td>${idx + 1}</td>
      <td><strong>${escaparHtml(art.nombre)}</strong></td>
      <td>${escaparHtml(art.pais)}</td>
      <td class="num">${art.canciones_registradas}</td>
      <td class="num">${art.reproducciones}</td>
      <td class="num">${formatearMinSeg(art.tiempo_acumulado_seg)}</td>
    </tr>
  `).join('');
}

function renderizarTablaUsuarios(usuarios, usuarioSeleccionado) {
  const tbody = document.getElementById('tbodyUsuarios');
  tbody.innerHTML = usuarios.map((u, idx) => {
    const activo = String(u.id) === String(usuarioSeleccionado);
    return `
      <tr class="clickable-row ${activo ? 'row-selected' : ''}" data-user-id="${u.id}">
        <td>${idx + 1}</td>
        <td><strong>${escaparHtml(u.nombre)}</strong></td>
        <td class="text-muted">${escaparHtml(u.correo)}</td>
        <td>${escaparHtml(u.pais)}</td>
        <td class="num">${u.canciones_distintas}</td>
        <td class="num">${u.reproducciones}</td>
        <td class="num">${formatearMinSeg(u.tiempo_acumulado_seg)}</td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('tr[data-user-id]').forEach(row => {
    row.addEventListener('click', () => {
      const selectUser = document.getElementById('filterUser');
      const id = row.dataset.userId;
      selectUser.value = selectUser.value === id ? 'all' : id;
      actualizarDashboard();
    });
  });
}

document.addEventListener('DOMContentLoaded', inicializarApp);
