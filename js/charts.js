// =====================================================
// CHARTS.JS - Chart.js Wrapper (Multi-dataset support)
// =====================================================

const FinCharts = {
    instances: {},

    getThemeColors() {
        const style = getComputedStyle(document.documentElement);
        return {
            text: style.getPropertyValue('--text-secondary').trim() || '#a0a0b8',
            grid: style.getPropertyValue('--border-color').trim() || 'rgba(255,255,255,0.08)',
            bg: style.getPropertyValue('--bg-card').trim() || 'rgba(30,30,50,0.8)'
        };
    },

    destroy(id) {
        if (this.instances[id]) {
            this.instances[id].destroy();
            delete this.instances[id];
        }
    },

    // Multi-dataset line chart
    // datasets: [{ label, data, color }]
    renderLine(canvasId, labels, datasets) {
        this.destroy(canvasId);
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const theme = this.getThemeColors();

        const chartDatasets = datasets.map(ds => ({
            label: ds.label,
            data: ds.data,
            borderColor: ds.color,
            backgroundColor: ds.color + '20',
            fill: true,
            tension: 0.4,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: ds.color
        }));

        this.instances[canvasId] = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: chartDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: datasets.length > 1, labels: { color: theme.text, usePointStyle: true, padding: 16 } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: R$ ${ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    x: { grid: { color: theme.grid }, ticks: { color: theme.text, maxTicksLimit: 12 } },
                    y: {
                        grid: { color: theme.grid },
                        ticks: {
                            color: theme.text,
                            callback: (v) => 'R$ ' + v.toLocaleString('pt-BR')
                        }
                    }
                }
            }
        });
    },

    // Doughnut chart
    renderDoughnut(canvasId, labels, data, colors) {
        this.destroy(canvasId);
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const theme = this.getThemeColors();

        this.instances[canvasId] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors || ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#3b82f6'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: theme.text, usePointStyle: true, padding: 12, font: { size: 12 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => `${ctx.label}: R$ ${ctx.parsed.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                        }
                    }
                }
            }
        });
    },

    // Multi-dataset bar chart
    renderBar(canvasId, labels, datasets) {
        this.destroy(canvasId);
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        const theme = this.getThemeColors();

        const chartDatasets = datasets.map(ds => ({
            label: ds.label,
            data: ds.data,
            backgroundColor: ds.color + '90',
            borderColor: ds.color,
            borderWidth: 1,
            borderRadius: 6,
            barPercentage: 0.7
        }));

        this.instances[canvasId] = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: chartDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: datasets.length > 1, labels: { color: theme.text, usePointStyle: true, padding: 16 } },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => `${ctx.dataset.label}: R$ ${ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: theme.text } },
                    y: {
                        grid: { color: theme.grid },
                        ticks: {
                            color: theme.text,
                            callback: (v) => 'R$ ' + v.toLocaleString('pt-BR')
                        }
                    }
                }
            }
        });
    },

    updateTheme() {
        Object.keys(this.instances).forEach(id => {
            const chart = this.instances[id];
            const theme = this.getThemeColors();
            if (chart.options.scales?.x) {
                chart.options.scales.x.grid.color = theme.grid;
                chart.options.scales.x.ticks.color = theme.text;
            }
            if (chart.options.scales?.y) {
                chart.options.scales.y.grid.color = theme.grid;
                chart.options.scales.y.ticks.color = theme.text;
            }
            if (chart.options.plugins?.legend?.labels) {
                chart.options.plugins.legend.labels.color = theme.text;
            }
            chart.update();
        });
    }
};
