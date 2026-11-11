import { apiGet, apiPost, escapeHtml, hideMessage, showMessage, toFixedNumber } from './api';

const STATUS_META = [
    ['sesuai', 'SESUAI', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    ['arahan', 'ARAHAN', 'border-amber-200 bg-amber-50 text-amber-700'],
    ['telat', 'TELAT', 'border-orange-200 bg-orange-50 text-orange-700'],
    ['tidak', 'TIDAK', 'border-rose-200 bg-rose-50 text-rose-700'],
];

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
    const standar = document.getElementById('reportStandar');
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
                <p class="mb-1"><span class="inline-block w-40 font-medium">Periode Rapor:</span> ${escapeHtml(data.periode_mulai)} s.d. ${escapeHtml(data.periode_selesai)}</p>
                <p class="mb-1"><span class="inline-block w-40 font-medium">Tanggal Cetak:</span> ${escapeHtml(data.tanggal_catat)}</p>
            `;

            const total = STATUS_META.reduce((sum, [key]) => sum + Number(data.ringkasan.status[key] || 0), 0);

            scores.innerHTML = STATUS_META.map(([key, title, color]) => `
                <div class="rounded-lg border py-3 ${color}">
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
                <tr>
                    <td class="px-3 py-2">${Number(row.no)}</td>
                    <td class="px-3 py-2">${escapeHtml(row.kegiatan)}</td>
                    <td class="px-3 py-2 text-center">${Number(row.sesuai)}</td>
                    <td class="px-3 py-2 text-center">${Number(row.arahan)}</td>
                    <td class="px-3 py-2 text-center">${Number(row.telat)}</td>
                    <td class="px-3 py-2 text-center">${Number(row.tidak)}</td>
                    <td class="px-3 py-2 text-center">${Number(row.libur)}</td>
                </tr>
            `).join('');

            standar.innerHTML = (data.standar_evaluasi || []).map((item) => `
                <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 font-semibold text-blue-700">
                    ${escapeHtml(item)}
                </span>
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
