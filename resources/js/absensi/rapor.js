import { apiGet, apiPost, escapeHtml, hideMessage, showMessage, toFixedNumber } from './api';

const STATUS_META = [
    ['sesuai', 'Sesuai', 'green'],
    ['arahan', 'Arahan', 'yellow'],
    ['telat', 'Telat', 'orange'],
    ['tidak', 'Tidak', 'red'],
];

const COLOR_CLASSES = {
    green: 'border-green-200 bg-green-50 text-green-700',
    yellow: 'border-yellow-200 bg-yellow-50 text-yellow-700',
    orange: 'border-orange-200 bg-orange-50 text-orange-700',
    red: 'border-red-200 bg-red-50 text-red-700',
};

const formatDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

export function initRapor() {
    const child = document.getElementById('reportAnak');

    if (!child) {
        return;
    }

    const period = document.getElementById('reportPeriode');
    const start = document.getElementById('reportMulai');
    const end = document.getElementById('reportSampai');
    const message = document.getElementById('reportMessage');
    const identity = document.getElementById('reportIdentity');
    const periodBox = document.getElementById('reportPeriod');
    const scores = document.getElementById('reportScores');
    const rows = document.getElementById('reportRows');
    const catatan = document.getElementById('catatanPerilaku');
    const rekomendasi = document.getElementById('rekomendasi');

    let reportData = null;

    const setPeriodDates = () => {
        const today = new Date();
        const todayValue = formatDate(today);
        end.value = todayValue;

        if (period.value === 'mingguan') {
            const startDate = new Date(today);
            startDate.setDate(today.getDate() - 6);
            start.value = formatDate(startDate);
        } else if (period.value === 'bulanan') {
            start.value = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
        } else if (period.value === 'harian') {
            start.value = todayValue;
        }

        const showDates = period.value === 'custom';
        start.classList.toggle('hidden', !showDates);
        end.classList.toggle('hidden', !showDates);
    };

    const loadReport = async () => {
        if (!child.value) {
            return;
        }

        hideMessage(message);

        if (period.value !== 'custom') {
            setPeriodDates();
        }

        if (!start.value || !end.value || start.value > end.value) {
            showMessage(message, 'Rentang tanggal rapor tidak valid.', false);
            return;
        }

        try {
            const data = await apiGet('report', { id: child.value, mulai: start.value, sampai: end.value });

            if (!data.anak) {
                throw new Error(data.message || 'Rapor tidak dapat dimuat.');
            }

            reportData = data;
            const anak = data.anak;

            identity.innerHTML = `
                <p class="mb-1"><span class="inline-block w-40 font-medium">ID &amp; Nama Anak:</span> ${escapeHtml(anak.id)} · ${escapeHtml(anak.nama)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Umur &amp; Agama:</span> ${escapeHtml(anak.umur)} tahun · ${escapeHtml(anak.agama)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Asal Daerah:</span> ${escapeHtml(anak.asal_daerah)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Angkatan:</span> ${escapeHtml(anak.angkatan)}</p>
            `;

            periodBox.innerHTML = `
                <p class="mb-1"><span class="inline-block w-32 font-medium">Tanggal Catat:</span> ${escapeHtml(data.tanggal_catat)}</p>
                <p class="mb-1"><span class="inline-block w-32 font-medium">Status:</span> ${escapeHtml(anak.status)}</p>
                <p class="mb-1"><span class="inline-block w-32 font-medium">Periode:</span> ${escapeHtml(data.periode_mulai)} s.d. ${escapeHtml(data.periode_selesai)}</p>
                <p class="mb-1"><span class="inline-block w-32 font-medium">Standar:</span> ${(data.standar_evaluasi || []).map(escapeHtml).join(', ')}</p>
            `;

            const total = STATUS_META.reduce((sum, [key]) => sum + Number(data.ringkasan.status[key] || 0), 0);

            scores.innerHTML = STATUS_META.map(([key, title, color]) => `
                <div class="rounded-lg border py-3 ${COLOR_CLASSES[color]}">
                    <p class="text-xs font-bold">${title}</p>
                    <p class="text-xl font-bold">${Number(data.ringkasan.status[key] || 0)}</p>
                    <p class="text-xs">${total ? ((Number(data.ringkasan.status[key] || 0) / total) * 100).toFixed(1) : '0.0'}%</p>
                </div>
            `).join('') + `
                <div class="rounded-lg border border-blue-200 bg-blue-50 py-3 text-blue-700">
                    <p class="text-xs font-bold">SKOR</p>
                    <p class="text-xl font-bold">${toFixedNumber(data.ringkasan.skor_total)}%</p>
                    <p class="text-xs">Total</p>
                </div>
            `;

            rows.innerHTML = data.ringkasan.detail.map((row) => `
                <tr class="border-b border-slate-100">
                    <td data-label="No" class="px-3 py-2 text-center">${Number(row.no)}</td>
                    <td data-label="Kegiatan" class="px-3 py-2">${escapeHtml(row.kegiatan)}</td>
                    <td data-label="Sesuai" class="px-3 py-2 text-center">${Number(row.sesuai)}</td>
                    <td data-label="Arahan" class="px-3 py-2 text-center">${Number(row.arahan)}</td>
                    <td data-label="Telat" class="px-3 py-2 text-center">${Number(row.telat)}</td>
                    <td data-label="Tidak" class="px-3 py-2 text-center">${Number(row.tidak)}</td>
                </tr>
            `).join('');

            catatan.value = data.catatan || '';
            rekomendasi.value = data.rekomendasi || '';
        } catch (error) {
            showMessage(message, error.message, false);
        }
    };

    period.addEventListener('change', loadReport);
    child.addEventListener('change', loadReport);
    start.addEventListener('change', () => period.value === 'custom' && loadReport());
    end.addEventListener('change', () => period.value === 'custom' && loadReport());

    document.getElementById('btnCetak')?.addEventListener('click', () => window.print());

    document.getElementById('btnSimpanCatatan')?.addEventListener('click', async (event) => {
        if (!reportData?.anak) {
            return;
        }

        event.currentTarget.disabled = true;

        try {
            const result = await apiPost('report_notes_save', {
                anak_id: child.value,
                periode_mulai: start.value,
                periode_selesai: end.value,
                catatan: catatan.value,
                rekomendasi: rekomendasi.value,
            });
            showMessage(message, result.message, true);
        } catch (error) {
            showMessage(message, error.message, false);
        } finally {
            event.currentTarget.disabled = false;
        }
    });

    loadReport();
}
