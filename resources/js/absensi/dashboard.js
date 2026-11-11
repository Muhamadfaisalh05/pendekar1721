import { apiGet, escapeHtml, hideMessage, showMessage, toFixedNumber } from './api';

export function initDashboard() {
    const chart = document.getElementById('trendChart');

    if (!chart) {
        return;
    }

    const message = document.getElementById('dashboardMessage');
    const emptyState = document.getElementById('chartEmpty');
    const toggle = document.getElementById('dashboardFilterToggle');
    const panel = document.getElementById('dashboardFilterPanel');
    const angkatan = document.getElementById('filterAngkatan');
    const status = document.getElementById('filterStatus');
    const mulai = document.getElementById('filterMulai');
    const sampai = document.getElementById('filterSampai');
    const reset = document.getElementById('resetDashboardFilters');

    const startClock = () => {
        const dateElement = document.getElementById('currentDate');
        const timeElement = document.getElementById('currentTime');

        if (!dateElement || !timeElement) {
            return;
        }

        const timezone = 'Asia/Jakarta';
        const dateFormatter = new Intl.DateTimeFormat('id-ID', {
            day: '2-digit', month: 'long', year: 'numeric', weekday: 'long', timeZone: timezone,
        });
        const timeFormatter = new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23', timeZone: timezone,
        });

        const update = () => {
            const now = new Date();
            dateElement.textContent = dateFormatter.format(now);
            timeElement.textContent = timeFormatter.format(now);
        };

        update();
        window.setInterval(update, 1000);
    };

    const load = async () => {
        hideMessage(message);

        try {
            const data = await apiGet('dashboard', {
                angkatan: angkatan?.value ?? '',
                status_anak: status?.value ?? '',
                mulai: mulai?.value ?? '',
                sampai: sampai?.value ?? '',
            });

            document.getElementById('statSesuai').textContent = `${toFixedNumber(data.presentase.sesuai)}%`;
            document.getElementById('statArahan').textContent = `${toFixedNumber(data.presentase.arahan)}%`;
            document.getElementById('statTelat').textContent = `${toFixedNumber(data.presentase.telat)}%`;
            document.getElementById('statTidak').textContent = `${toFixedNumber(data.presentase.tidak)}%`;
            const skor = document.getElementById('statSkor');

            if (skor) {
                skor.textContent = `${toFixedNumber(data.presentase.sesuai)}%`;
            }
            document.getElementById('totalAnak').textContent = data.total_anak;
            document.getElementById('totalKegiatan').textContent = data.total_kegiatan;
            document.getElementById('totalPenilaian').textContent = data.total_penilaian;

            const trend = data.tren ?? [];
            const max = Math.max(1, ...trend.map((item) => Number(item.total)));

            emptyState.classList.toggle('hidden', trend.length > 0);
            chart.innerHTML = trend.map((item) => {
                const total = Number(item.total);
                const percent = total ? (Number(item.sesuai) / total) * 100 : 0;

                return `
                    <div class="grid grid-cols-[90px_1fr_120px] items-center gap-3 text-xs">
                        <span class="text-slate-500">${escapeHtml(item.tanggal)}</span>
                        <div class="h-5 overflow-hidden rounded bg-slate-100">
                            <div class="h-full bg-indigo-500" style="width:${Math.max(3, (total / max) * 100)}%"
                                title="${total} penilaian; ${percent.toFixed(1)}% sesuai"></div>
                        </div>
                        <span class="text-right text-slate-600">${total} nilai · ${percent.toFixed(0)}% sesuai</span>
                    </div>
                `;
            }).join('');
        } catch (error) {
            showMessage(message, error.message, false);
        }
    };

    [angkatan, status, mulai, sampai].forEach((input) => input?.addEventListener('change', load));

    toggle?.addEventListener('click', () => {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isOpen));
        panel?.classList.toggle('hidden', isOpen);
    });

    reset?.addEventListener('click', () => {
        if (angkatan) angkatan.value = '';
        if (status) status.value = 'Aktif';
        if (mulai) mulai.value = '';
        if (sampai) sampai.value = '';
        load();
    });

    startClock();
    load();
}
