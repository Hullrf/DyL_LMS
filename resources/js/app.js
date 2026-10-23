import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Selector de categoría con autocompletado + creación inline (ver
// resources/views/components/categoria-selector.blade.php).
Alpine.data('categoriaSelector', (config) => ({
    categorias: config.categorias ?? [],
    query: config.selectedNombre ?? '',
    selectedId: config.selectedId ?? null,
    selectedNombre: config.selectedNombre ?? '',
    crearUrl: config.crearUrl,
    open: false,
    creando: false,
    error: '',

    get filtradas() {
        const q = this.query.trim().toLowerCase();
        if (!q) return this.categorias;
        return this.categorias.filter((c) => c.nombre.toLowerCase().includes(q));
    },

    get coincideExistente() {
        const q = this.query.trim().toLowerCase();
        return this.categorias.some((c) => c.nombre.trim().toLowerCase() === q);
    },

    get puedeCrear() {
        return this.query.trim().length > 0 && !this.coincideExistente && !this.creando;
    },

    onInput() {
        this.error = '';
        this.open = true;
        if (this.query !== this.selectedNombre) {
            this.selectedId = null;
        }
    },

    seleccionar(cat) {
        this.selectedId = cat.id;
        this.selectedNombre = cat.nombre;
        this.query = cat.nombre;
        this.open = false;
        this.error = '';
    },

    async crear() {
        if (!this.puedeCrear) return;
        this.creando = true;
        this.error = '';
        try {
            const { data } = await window.axios.post(this.crearUrl, { nombre: this.query.trim() });
            this.categorias.push(data);
            this.seleccionar(data);
        } catch (e) {
            this.error = e.response?.data?.message ?? 'No se pudo crear la categoría.';
        } finally {
            this.creando = false;
        }
    },
}));

Alpine.start();

import Chart from 'chart.js/auto';
window.Chart = Chart;

// Tema de Chart.js: ejes y cuadrícula legibles en claro/oscuro, sin animación si el usuario
// prefiere menos movimiento, y redibujo al alternar el tema (evento 'themechange').
function dylAplicarTemaCharts() {
    const oscuro = document.documentElement.classList.contains('dark');
    Chart.defaults.color = oscuro ? '#94a3b8' : '#64748b';
    Chart.defaults.borderColor = oscuro ? '#263247' : '#e5e7eb';
    Chart.defaults.animation = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : {};
}
dylAplicarTemaCharts();
window.addEventListener('themechange', () => {
    dylAplicarTemaCharts();
    // Cada gráfico ya resolvió (y cacheó) sus colores al crearse; cambiar solo los defaults no basta.
    Object.values(Chart.instances).forEach((c) => {
        Object.values(c.options.scales ?? {}).forEach((s) => {
            s.ticks.color = Chart.defaults.color;
            s.grid.color = Chart.defaults.borderColor;
        });
        const leyenda = c.options.plugins?.legend?.labels;
        if (leyenda) leyenda.color = Chart.defaults.color;
        c.update();
    });
});
