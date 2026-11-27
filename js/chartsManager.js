export class ChartsManager {
  constructor() {
    this.charts = {};
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = '#282d38';
    Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
  }

  renderMensual(etiquetas, valores) {
    const ctx = document.getElementById('chartMensual');
    if (this.charts.mensual) this.charts.mensual.destroy();

    this.charts.mensual = new Chart(ctx, {
      type: 'line',
      data: {
        labels: etiquetas,
        datasets: [{
          label: 'Reproducciones',
          data: valores,
          borderColor: '#e11d48',
          backgroundColor: 'rgba(225, 29, 72, 0.15)',
          borderWidth: 2.5,
          fill: true,
          tension: 0.35,
          pointBackgroundColor: '#e11d48',
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  renderGeneros(generosStats) {
    const ctx = document.getElementById('chartGeneros');
    if (this.charts.generos) this.charts.generos.destroy();

    const etiquetas = generosStats.map(g => g.nombre);
    const minutos = generosStats.map(g => Math.round(g.tiempo_escuchado_seg / 60));

    this.charts.generos = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: etiquetas,
        datasets: [{
          data: minutos,
          backgroundColor: ['#e11d48', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { boxWidth: 12, padding: 12 } },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.raw} min`
            }
          }
        }
      }
    });
  }

  renderDias(etiquetas, valores) {
    const ctx = document.getElementById('chartDias');
    if (this.charts.dias) this.charts.dias.destroy();

    this.charts.dias = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: etiquetas,
        datasets: [{
          label: 'Reproducciones',
          data: valores,
          backgroundColor: '#3b82f6',
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  renderPaises(paisesStats) {
    const ctx = document.getElementById('chartPaises');
    if (this.charts.paises) this.charts.paises.destroy();

    this.charts.paises = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: paisesStats.map(p => `${p.pais} (${p.artistas_registrados} art.)`),
        datasets: [{
          label: 'Reproducciones',
          data: paisesStats.map(p => p.reproducciones),
          backgroundColor: '#10b981',
          borderRadius: 6
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }
}