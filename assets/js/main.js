/* ================================================================
   RUMS — Main JavaScript
   ================================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Sidebar & Backdrop ─────────────────────────────────────
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar   = document.getElementById('sidebar');

    // Inject backdrop element once
    let backdrop = document.getElementById('sidebarBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id        = 'sidebarBackdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    function isMobile() { return window.innerWidth < 768; }

    function openMobileSidebar() {
        sidebar.classList.add('show');
        backdrop.classList.add('show');
        document.body.style.overflow = 'hidden'; // prevent scroll-through
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }

    function toggleDesktopSidebar() {
        document.body.classList.toggle('sidebar-collapsed');
    }

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.contains('show') ? closeMobileSidebar() : openMobileSidebar();
            } else {
                toggleDesktopSidebar();
            }
        });
    }

    // Close sidebar when backdrop is clicked (mobile)
    backdrop.addEventListener('click', closeMobileSidebar);

    // Close sidebar when a nav link is clicked on mobile
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) closeMobileSidebar();
        });
    });

    // On resize: clean up mobile state when switching to desktop
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (!isMobile()) {
                closeMobileSidebar();  // remove mobile classes
            }
        }, 150);
    });

    // ── Auto-dismiss flash alerts ──────────────────────────────
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            try { bootstrap.Alert.getOrCreateInstance(alert)?.close(); } catch {}
        }, 5000);
    });

    // ── Confirm dangerous actions ──────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // ── Phone formatting ───────────────────────────────────────
    document.querySelectorAll('input[name="phone"], input[type="tel"]').forEach(input => {
        input.addEventListener('blur', () => formatKenyanPhone(input));
    });

    // ── Make all tables responsive ─────────────────────────────
    document.querySelectorAll('table.table').forEach(tbl => {
        if (!tbl.closest('.table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            tbl.parentNode.insertBefore(wrapper, tbl);
            wrapper.appendChild(tbl);
        }
    });

    // ── Initialise charts ──────────────────────────────────────
    initDashboardCharts();
    initRevenueChartReport();
    initMethodChart();

});

/* ── Chart defaults (global) ──────────────────────────────────── */
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family  = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.font.size    = 12;
    Chart.defaults.color        = '#6b7280';
    Chart.defaults.responsive   = true;
    Chart.defaults.maintainAspectRatio = true;
}

/* ── Dashboard Charts ─────────────────────────────────────────── */
function initDashboardCharts() {
    const revCanvas = document.getElementById('revenueChart');
    if (!revCanvas || typeof revenueLabels === 'undefined') return;

    new Chart(revCanvas, {
        type: 'bar',
        data: {
            labels:   revenueLabels,
            datasets: [{
                label: 'Revenue (Ksh)',
                data:  revenueData,
                backgroundColor: 'rgba(26,86,219,.75)',
                borderColor:     '#1a56db',
                borderWidth:     2,
                borderRadius:    6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() }
                },
                x: { grid: { display: false } }
            }
        }
    });

    const occCanvas = document.getElementById('occupancyChart');
    if (!occCanvas || typeof occupancyData === 'undefined') return;

    new Chart(occCanvas, {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Occupied', 'Maintenance', 'Reserved'],
            datasets: [{
                data: [
                    occupancyData.available,
                    occupancyData.occupied,
                    occupancyData.maintenance,
                    occupancyData.reserved,
                ],
                backgroundColor: ['#10b981','#3b82f6','#f59e0b','#06b6d4'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 10 }
                },
                tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed } }
            }
        }
    });
}

/* ── Financial Report Charts ──────────────────────────────────── */
function initRevenueChartReport() {
    const canvas = document.getElementById('revenueChart');
    if (!canvas || typeof revData === 'undefined') return;

    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue (Ksh)',
                data:  revData,
                backgroundColor: 'rgba(26,86,219,.7)',
                borderRadius: 5,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'Ksh ' + Number(v).toLocaleString() }
                },
                x: { grid: { display: false } }
            }
        }
    });
}

function initMethodChart() {
    const canvas = document.getElementById('methodChart');
    if (!canvas || typeof methodLabels === 'undefined') return;

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: methodLabels,
            datasets: [{
                data: methodData,
                backgroundColor: ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            cutout: '55%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        }
    });
}

/* ── Helpers ──────────────────────────────────────────────────── */
function formatKenyanPhone(input) {
    let val = input.value.replace(/\D/g, '');
    if (val.startsWith('0') && val.length === 10) {
        input.value = '0' + val.substring(1);
    }
}

function formatMoney(amount) {
    return 'Ksh ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 2 });
}
