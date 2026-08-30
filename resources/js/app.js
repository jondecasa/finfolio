import './bootstrap';

import Alpine from 'alpinejs';
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    DoughnutController,
    ArcElement,
} from 'chart.js';

Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    DoughnutController,
    ArcElement,
);

Chart.defaults.font.family =
    "Figtree, Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";
Chart.defaults.color = '#8a8a8e';

const css = getComputedStyle(document.documentElement);
const GAIN = (css.getPropertyValue('--c-gain') || '#30d67b').trim();
const LOSS = (css.getPropertyValue('--c-loss') || '#ff5a5f').trim();

const CURRENCY_SYMBOLS = {
    EUR: '€', USD: '$', GBP: '£', CHF: 'CHF ', JPY: '¥',
    CAD: 'CA$', AUD: 'A$', SEK: 'kr ', NOK: 'kr ', DKK: 'kr ', PLN: 'zł ',
};

function formatCurrency(value, currency = 'EUR') {
    const symbol = CURRENCY_SYMBOLS[currency] || `${currency} `;
    const sign = value < 0 ? '-' : '';
    const n = Math.abs(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return `${sign}${symbol}${n}`;
}

/**
 * Area line chart used on Home / Net Worth.
 */
function areaChart(canvas, payload) {
    const ctx = canvas.getContext('2d');
    const points = payload.points || [];
    const up = (payload.change ?? 0) >= 0;
    const color = up ? GAIN : LOSS;

    const gradient = ctx.createLinearGradient(0, 0, 0, canvas.offsetHeight || 260);
    gradient.addColorStop(0, hexToRgba(color, 0.28));
    gradient.addColorStop(1, hexToRgba(color, 0));

    const data = {
        labels: points.map((p) => p.t),
        datasets: [
            {
                data: points.map((p) => p.v),
                borderColor: color,
                borderWidth: 2,
                fill: true,
                backgroundColor: gradient,
                tension: 0.25,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointHoverBackgroundColor: color,
                pointHoverBorderColor: '#000',
            },
        ],
    };

    const baseline = payload.start_value ?? points[0]?.v ?? 0;

    return new Chart(canvas, {
        type: 'line',
        data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 6, bottom: 6 } },
            scales: {
                x: { type: 'category', display: false, grid: { display: false } },
                y: { display: false, grid: { display: false }, grace: '12%' },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    displayColors: false,
                    backgroundColor: '#1c1c1e',
                    borderColor: '#38383c',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 10,
                    callbacks: {
                        title: (items) => {
                            const raw = items[0].label;
                            const d = new Date(raw);
                            return Number.isNaN(d.getTime())
                                ? raw
                                : d.toLocaleString(undefined, {
                                      month: 'short',
                                      day: 'numeric',
                                      hour: '2-digit',
                                      minute: '2-digit',
                                  });
                        },
                        label: (item) =>
                            payload.hidden
                                ? '••••••'
                                : formatCurrency(item.parsed.y, payload.currency || 'EUR'),
                    },
                },
            },
            elements: { line: { capBezierPoints: true } },
        },
        plugins: [baselinePlugin(baseline)],
    });
}

function baselinePlugin(baseline) {
    return {
        id: 'baseline',
        afterDatasetsDraw(chart) {
            const { ctx, chartArea, scales } = chart;
            if (!scales.y) return;
            const y = scales.y.getPixelForValue(baseline);
            if (Number.isNaN(y)) return;
            ctx.save();
            ctx.setLineDash([4, 4]);
            ctx.strokeStyle = 'rgba(255,255,255,0.18)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(chartArea.left, y);
            ctx.lineTo(chartArea.right, y);
            ctx.stroke();
            ctx.restore();
        },
    };
}

function hexToRgba(hex, alpha) {
    const h = hex.replace('#', '');
    const bigint = parseInt(h.length === 3 ? h.split('').map((c) => c + c).join('') : h, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Full allocation ring. One solid colour per segment, rounded caps, thin ring.
 * The hole starts empty; `payload.onHover(segment|null)` and
 * `payload.onClick(segment|null)` let the caller fill it on interaction.
 */
function ringChart(canvas, payload) {
    const segments = payload.segments || [];
    const onHover = payload.onHover || (() => {});
    const onClick = payload.onClick || (() => {});
    const segAt = (els) => (els && els.length ? segments[els[0].index] ?? null : null);

    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: segments.map((s) => s.label),
            datasets: [
                {
                    data: segments.map((s) => s.value),
                    backgroundColor: segments.map((s) => s.color),
                    borderWidth: 0,
                    borderRadius: segments.length > 1 ? 8 : 0,
                    spacing: segments.length > 1 ? 3 : 0,
                    hoverOffset: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '74%',
            animation: { animateRotate: true, duration: 500 },
            onHover: (event, els, chart) => {
                onHover(segAt(els));
                chart.canvas.style.cursor = els && els.length ? 'pointer' : 'default';
            },
            onClick: (event, els) => onClick(segAt(els)),
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
            },
        },
    });
}

window.Finfolio = { areaChart, ringChart, formatCurrency };

/* ------------------------------------------------------------------ */
/* Alpine components                                                    */
/* ------------------------------------------------------------------ */

Alpine.data('netWorthChart', (initial) => ({
    range: initial.range || '1W',
    loading: false,
    chart: null,
    payload: initial.payload,
    hidden: initial.hidden || false,
    init() {
        this.render();
    },
    render() {
        if (this.chart) this.chart.destroy();
        this.chart = window.Finfolio.areaChart(this.$refs.canvas, { ...this.payload, hidden: this.hidden });
    },
    async select(range) {
        if (range === this.range) return;
        this.range = range;
        this.loading = true;
        try {
            const url = new URL(initial.endpoint, window.location.origin);
            url.searchParams.set('range', range);
            if (initial.accountId) url.searchParams.set('account_id', initial.accountId);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            this.payload = await res.json();
            this.$dispatch('series-updated', this.payload);
            this.render();
        } finally {
            this.loading = false;
        }
    },
}));

Alpine.data('assetSearch', (config = {}) => ({
    query: '',
    type: config.type || '',
    results: [],
    loading: false,
    selected: null,
    open: false,
    timer: null,
    onInput() {
        clearTimeout(this.timer);
        this.selected = null;
        if (this.query.trim().length < 1) {
            this.results = [];
            this.open = false;
            return;
        }
        this.timer = setTimeout(() => this.run(), 280);
    },
    async run() {
        this.loading = true;
        this.open = true;
        try {
            const url = new URL('/api/search', window.location.origin);
            url.searchParams.set('q', this.query.trim());
            if (this.type) url.searchParams.set('type', this.type);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            this.results = data.results || [];
        } catch (e) {
            this.results = [];
        } finally {
            this.loading = false;
        }
    },
    choose(row) {
        this.selected = row;
        this.query = `${row.symbol} · ${row.name}`;
        this.open = false;
        this.$dispatch('asset-selected', row);
    },
}));

window.Alpine = Alpine;
Alpine.start();

/* ------------------------------------------------------------------ */
/* PWA: register the service worker                                     */
/* ------------------------------------------------------------------ */
if ('serviceWorker' in navigator) {
    const registerSW = () => navigator.serviceWorker.register('/sw.js').catch(() => {});
    if (document.readyState === 'complete') {
        registerSW();
    } else {
        window.addEventListener('load', registerSW);
    }
}
