// Dashboard Charts - Chart.js Integration for Analytics
// Handles rendering of spend trends and partner performance charts

class DashboardCharts {
    constructor() {
        this.charts = new Map();
        this.chartData = {};
        this.chartConfigs = {
            spendTrend: {
                type: 'line',
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return 'ETB ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Amount (ETB)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'ETB ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            },
            partnerPerformance: {
                type: 'bar',
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + '% OTD';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Carrier Partner'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'On-Time Delivery %'
                            },
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            }
        };
    }

    init() {
        this.loadChartJS();
        this.loadChartData();
        this.setupEventListeners();
    }

    loadChartJS() {
        // Check if Chart.js is already loaded
        if (typeof Chart !== 'undefined') {
            this.initializeCharts();
        } else {
            // Load Chart.js dynamically
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js';
            script.onload = () => this.initializeCharts();
            script.onerror = () => this.showChartError('Failed to load chart library');
            document.head.appendChild(script);
        }
    }

    loadChartData() {
        // Get chart data from the hidden JSON script
        const dashboardData = document.getElementById('dashboard-data');
        if (dashboardData) {
            try {
                const data = JSON.parse(dashboardData.textContent);
                this.chartData = {
                    spendTrend: data.spend_trend || [],
                    partnerPerformance: data.partner_performance || []
                };
            } catch (error) {
                console.error('Error parsing chart data:', error);
            }
        }
    }

    initializeCharts() {
        this.createSpendTrendChart();
        this.createPartnerPerformanceChart();
    }

    createSpendTrendChart() {
        const canvas = document.getElementById('spend-trend-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Prepare data
        const labels = this.chartData.spendTrend.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        const data = this.chartData.spendTrend.map(item => parseFloat(item.daily_spend));

        const config = {
            ...this.chartConfigs.spendTrend,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Spend',
                    data: data,
                    borderColor: 'var(--primary-blue)',
                    backgroundColor: 'rgba(0, 51, 102, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'var(--primary-blue)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            }
        };

        this.charts.set('spend-trend', new Chart(ctx, config));
    }

    createPartnerPerformanceChart() {
        const canvas = document.getElementById('partner-performance-chart');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Prepare data
        const labels = this.chartData.partnerPerformance.map(item => {
            // Truncate long company names
            return item.company_name.length > 15 ?
                item.company_name.substring(0, 15) + '...' :
                item.company_name;
        });

        const data = this.chartData.partnerPerformance.map(item => parseFloat(item.otd_percentage));

        const config = {
            ...this.chartConfigs.partnerPerformance,
            data: {
                labels: labels,
                datasets: [{
                    label: 'On-Time Delivery',
                    data: data,
                    backgroundColor: data.map(value => {
                        if (value >= 95) return '#4CAF50'; // Green
                        if (value >= 85) return '#FF9800'; // Orange
                        return '#F44336'; // Red
                    }),
                    borderColor: data.map(value => {
                        if (value >= 95) return '#388E3C';
                        if (value >= 85) return '#F57C00';
                        return '#D32F2F';
                    }),
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            }
        };

        this.charts.set('partner-performance', new Chart(ctx, config));
    }

    updateChart(chartId, newData) {
        const chart = this.charts.get(chartId);
        if (chart && newData) {
            if (chartId === 'spend-trend') {
                const labels = newData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                });
                const data = newData.map(item => parseFloat(item.daily_spend));

                chart.data.labels = labels;
                chart.data.datasets[0].data = data;
            } else if (chartId === 'partner-performance') {
                const labels = newData.map(item => {
                    return item.company_name.length > 15 ?
                        item.company_name.substring(0, 15) + '...' :
                        item.company_name;
                });
                const data = newData.map(item => parseFloat(item.otd_percentage));

                chart.data.labels = labels;
                chart.data.datasets[0].data = data;
                chart.data.datasets[0].backgroundColor = data.map(value => {
                    if (value >= 95) return '#4CAF50';
                    if (value >= 85) return '#FF9800';
                    return '#F44336';
                });
                chart.data.datasets[0].borderColor = data.map(value => {
                    if (value >= 95) return '#388E3C';
                    if (value >= 85) return '#F57C00';
                    return '#D32F2F';
                });
            }

            chart.update('active');
        }
    }

    refreshCharts() {
        // Fetch updated data from server
        this.fetchUpdatedData().then(newData => {
            if (newData) {
                this.chartData = newData;
                this.updateChart('spend-trend', newData.spend_trend);
                this.updateChart('partner-performance', newData.partner_performance);
            }
        });
    }

    async fetchUpdatedData() {
        try {
            const response = await fetch('api/get_chart_data.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                return data.success ? data.data : null;
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
        }
        return null;
    }

    showChartError(message) {
        const containers = ['spend-trend-chart', 'partner-performance-chart'];
        containers.forEach(id => {
            const canvas = document.getElementById(id);
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#666';
                ctx.font = '14px Arial';
                ctx.textAlign = 'center';
                ctx.fillText(message, canvas.width / 2, canvas.height / 2);
            }
        });
    }

    setupEventListeners() {
        // Handle window resize for responsive charts
        window.addEventListener('resize', () => {
            this.charts.forEach(chart => {
                chart.resize();
            });
        });

        // Refresh charts when dashboard tab becomes active
        document.addEventListener('tabActivated', (e) => {
            if (e.detail && e.detail.tabId === 'dashboard') {
                setTimeout(() => this.refreshCharts(), 500);
            }
        });
    }

    destroy() {
        this.charts.forEach(chart => {
            chart.destroy();
        });
        this.charts.clear();
    }
}

// Global instance
const dashboardCharts = new DashboardCharts();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the dashboard page
    if (document.getElementById('spend-trend-chart') || document.getElementById('partner-performance-chart')) {
        dashboardCharts.init();
    }
});
