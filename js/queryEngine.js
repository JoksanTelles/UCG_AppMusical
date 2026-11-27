export class QueryEngine {
  constructor(db) {
    this.db = db;
    // Índice desnormalizado en memoria equivalente a hacer INNER JOIN entre tablas maestras
    this.catalogoCompleto = this.db.canciones.map(cancion => {
      const album = this.db.albumes.find(a => a.id === cancion.album_id);
      const artista = this.db.artistas.find(art => art.id === album.artista_id);
      const genero = this.db.generos.find(g => g.id === cancion.genero_id);

      return {
        ...cancion,
        album_nombre: album ? album.nombre : 'Desconocido',
        artista_id: artista ? artista.id : null,
        artista_nombre: artista ? artista.nombre : 'Desconocido',
        artista_pais: artista ? artista.pais : 'Desconocido',
        genero_nombre: genero ? genero.nombre : 'Sin género'
      };
    });
  }

  // Equivalente a WHERE YEAR(fecha) = ? AND QUARTER(fecha) = ?
  filtrarHistorial(anio = 'all', trimestre = 'all') {
    return this.db.historial_reproducciones.filter(reg => {
      const fecha = new Date(reg.fecha_reproduccion);
      const regAnio = fecha.getFullYear().toString();
      const mes = fecha.getMonth() + 1; // 1 a 12

      if (anio !== 'all' && regAnio !== anio) return false;

      if (trimestre !== 'all') {
        if (trimestre === 'Q1' && (mes < 1 || mes > 3)) return false;
        if (trimestre === 'Q2' && (mes < 4 || mes > 6)) return false;
        if (trimestre === 'Q3' && (mes < 7 || mes > 9)) return false;
        if (trimestre === 'Q4' && (mes < 10 || mes > 12)) return false;
      }
      return true;
    });
  }

  obtenerAniosDisponibles() {
    const anios = new Set(
      this.db.historial_reproducciones.map(r => new Date(r.fecha_reproduccion).getFullYear())
    );
    return Array.from(anios).sort((a, b) => b - a);
  }

  // Bloque A: Estadísticas Generales
  obtenerEstadisticasGenerales(historialFiltrado) {
    const totalCanciones = this.db.canciones.length;
    const totalArtistas = this.db.artistas.length;
    const totalGeneros = this.db.generos.length;
    const totalReproducciones = historialFiltrado.length;

    const tiempoTotalSeg = historialFiltrado.reduce(
      (acc, item) => acc + item.duracion_escuchada_seg,
      0
    );

    const sumaDuracionCatalogo = this.db.canciones.reduce(
      (acc, c) => acc + c.duracion_seg,
      0
    );
    const promedioDuracionSeg = totalCanciones > 0
      ? Math.round(sumaDuracionCatalogo / totalCanciones)
      : 0;

    return {
      totalCanciones,
      totalArtistas,
      totalGeneros,
      totalReproducciones,
      tiempoTotalSeg,
      promedioDuracionSeg
    };
  }

  // Bloque B: Estadísticas de Canciones (LEFT JOIN para incluir 0 reproducciones)
  obtenerEstadisticasCanciones(historialFiltrado) {
    const conteoPorCancion = historialFiltrado.reduce((acc, reg) => {
      if (!acc[reg.cancion_id]) {
        acc[reg.cancion_id] = { reproducciones: 0, tiempo_acumulado_seg: 0 };
      }
      acc[reg.cancion_id].reproducciones += 1;
      acc[reg.cancion_id].tiempo_acumulado_seg += reg.duracion_escuchada_seg;
      return acc;
    }, {});

    const cancionesConStats = this.catalogoCompleto.map(c => {
      const stats = conteoPorCancion[c.id] || { reproducciones: 0, tiempo_acumulado_seg: 0 };
      return { ...c, ...stats };
    });

    const masEscuchadas = [...cancionesConStats]
      .filter(c => c.reproducciones > 0)
      .sort((a, b) => b.reproducciones - a.reproducciones || b.tiempo_acumulado_seg - a.tiempo_acumulado_seg)
      .slice(0, 10);

    const menosReproducidas = [...cancionesConStats]
      .sort((a, b) => a.reproducciones - b.reproducciones || a.tiempo_acumulado_seg - b.tiempo_acumulado_seg)
      .slice(0, 10);

    return { masEscuchadas, menosReproducidas };
  }

  // Bloque C: Estadísticas de Artistas y Países
  obtenerEstadisticasArtistas(historialFiltrado) {
    // Canciones registradas por artista
    const cancionesPorArtista = this.catalogoCompleto.reduce((acc, c) => {
      acc[c.artista_id] = (acc[c.artista_id] || 0) + 1;
      return acc;
    }, {});

    // Reproducciones por artista
    const statsPorArtista = {};
    this.db.artistas.forEach(art => {
      statsPorArtista[art.id] = {
        ...art,
        canciones_registradas: cancionesPorArtista[art.id] || 0,
        reproducciones: 0,
        tiempo_acumulado_seg: 0
      };
    });

    const statsPorPais = {};
    this.db.artistas.forEach(art => {
      if (!statsPorPais[art.pais]) {
        statsPorPais[art.pais] = { pais: art.pais, artistas_registrados: 0, reproducciones: 0 };
      }
      statsPorPais[art.pais].artistas_registrados += 1;
    });

    historialFiltrado.forEach(reg => {
      const cancion = this.catalogoCompleto.find(c => c.id === reg.cancion_id);
      if (!cancion) return;

      if (statsPorArtista[cancion.artista_id]) {
        statsPorArtista[cancion.artista_id].reproducciones += 1;
        statsPorArtista[cancion.artista_id].tiempo_acumulado_seg += reg.duracion_escuchada_seg;
      }
      if (statsPorPais[cancion.artista_pais]) {
        statsPorPais[cancion.artista_pais].reproducciones += 1;
      }
    });

    const rankingArtistas = Object.values(statsPorArtista)
      .sort((a, b) => b.reproducciones - a.reproducciones || b.canciones_registradas - a.canciones_registradas)
      .slice(0, 10);

    const rankingPaises = Object.values(statsPorPais)
      .sort((a, b) => b.reproducciones - a.reproducciones);

    return { rankingArtistas, rankingPaises };
  }

  // Bloque D: Estadísticas de Géneros Musicales
  obtenerEstadisticasGeneros(historialFiltrado) {
    const mapaGeneros = {};
    this.db.generos.forEach(g => {
      mapaGeneros[g.id] = {
        id: g.id,
        nombre: g.nombre,
        cantidad_canciones: 0,
        reproducciones: 0,
        tiempo_escuchado_seg: 0
      };
    });

    this.db.canciones.forEach(c => {
      if (mapaGeneros[c.genero_id]) mapaGeneros[c.genero_id].cantidad_canciones += 1;
    });

    historialFiltrado.forEach(reg => {
      const cancion = this.catalogoCompleto.find(c => c.id === reg.cancion_id);
      if (cancion && mapaGeneros[cancion.genero_id]) {
        mapaGeneros[cancion.genero_id].reproducciones += 1;
        mapaGeneros[cancion.genero_id].tiempo_escuchado_seg += reg.duracion_escuchada_seg;
      }
    });

    return Object.values(mapaGeneros).sort((a, b) => b.tiempo_escuchado_seg - a.tiempo_escuchado_seg);
  }

  // Bloque E: Estadísticas Temporales del Historial
  obtenerEstadisticasTemporales(historialFiltrado) {
    const nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const nombresDias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    const porMes = Array(12).fill(0);
    const porDia = Array(7).fill(0);

    historialFiltrado.forEach(reg => {
      const fecha = new Date(reg.fecha_reproduccion);
      porMes[fecha.getMonth()] += 1;
      porDia[fecha.getDay()] += 1;
    });

    const maxMesVal = Math.max(...porMes);
    const mesPico = maxMesVal > 0 ? nombresMeses[porMes.indexOf(maxMesVal)] : '—';

    const maxDiaVal = Math.max(...porDia);
    const diaPico = maxDiaVal > 0 ? nombresDias[porDia.indexOf(maxDiaVal)] : '—';

    // Reordenar días para mostrar Lunes a Domingo
    const ordenDias = [1, 2, 3, 4, 5, 6, 0];
    const etiquetasDias = ordenDias.map(idx => nombresDias[idx]);
    const valoresDias = ordenDias.map(idx => porDia[idx]);

    return {
      etiquetasMeses: nombresMeses,
      valoresMeses: porMes,
      mesPico,
      etiquetasDias,
      valoresDias,
      diaPico
    };
  }
}