import { apiGet, escapeHtml, hideMessage, showMessage, toFixedNumber } from './api';

const STATUS_META = [
    ['sesuai', 'SESUAI', 'green'],
    ['arahan', 'ARAHAN', 'yellow'],
    ['telat', 'TERLAMBAT', 'orange'],
    ['tidak', 'TIDAK', 'red'],
];

const COLOR_CLASSES = {
    green: { box: 'border-green-200 bg-green-50', title: 'text-green-700', value: 'text-green-600' },
    yellow: { box: 'border-yellow-200 bg-yellow-50', title: 'text-yellow-700', value: 'text-yellow-600' },
    orange: { box: 'border-orange-200 bg-orange-50', title: 'text-orange-700', value: 'text-orange-600' },
    red: { box: 'border-red-200 bg-red-50', title: 'text-red-700', value: 'text-red-600' },
};

export function initSummary() {
    const childSelect = document.getElementById('pilihAnak');

    if (!childSelect) {
        return;
    }

    const startInput = document.getElementById('mulai');
    const endInput = document.getElementById('sampai');
    const profile = document.getElementById('anakProfile');
    const cards = document.getElementById('summaryCards');
    const rows = document.getElementById('detailRows');
    const message = document.getElementById('summaryMessage');

    const load = async () => {
        hideMessage(message);

        if (!childSelect.value) {
            profile.innerHTML = '<p class="text-slate-500">Belum ada data anak untuk ditampilkan.</p>';
            cards.innerHTML = '';
            rows.innerHTML = '';
            return;
        }

        try {
            const data = await apiGet('summary', {
                id: childSelect.value,
                mulai: startInput.value,
                sampai: endInput.value,
            });

            if (!data.anak) {
                throw new Error('Data anak tidak ditemukan.');
            }

            const anak = data.anak;
            const initials = String(anak.nama || '').split(/\s+/).slice(0, 2)
                .map((part) => part.charAt(0)).join('').toUpperCase();

            profile.innerHTML = `
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-blue-600 text-2xl font-bold text-white">
                    ${escapeHtml(initials)}
                </div>
                <div>
                    <h3 class="text-lg font-bold">${escapeHtml(anak.nama)}</h3>
                    <p class="text-sm text-slate-500">${escapeHtml(anak.jenis_kelamin)} · ${escapeHtml(anak.status)}</p>
                </div>
                <div class="text-sm md:ml-auto md:text-right">
                    <p class="text-slate-500">Asal Daerah &amp; Agama: ${escapeHtml(anak.asal_daerah)} · ${escapeHtml(anak.agama)}</p>
                    <p class="text-slate-500">ID, Umur &amp; Angkatan: ${escapeHtml(anak.id)} · ${escapeHtml(anak.umur)} tahun · ${escapeHtml(anak.angkatan)}</p>
                </div>
            `;

            const total = STATUS_META.reduce((sum, [key]) => sum + Number(data.status[key] || 0), 0);

            cards.innerHTML = STATUS_META.map(([key, title, color]) => `
                <div class="rounded-lg border py-4 ${COLOR_CLASSES[color].box}">
                    <p class="mb-1 text-xs font-bold ${COLOR_CLASSES[color].title}">${title}</p>
                    <p class="text-2xl font-bold ${COLOR_CLASSES[color].value}">${Number(data.status[key] || 0)}</p>
                    <p class="text-xs ${COLOR_CLASSES[color].value}">${total ? ((Number(data.status[key] || 0) / total) * 100).toFixed(1) : '0.0'}%</p>
                </div>
            `).join('') + `
                <div class="rounded-lg border border-blue-200 bg-blue-50 py-4">
                    <p class="mb-1 text-xs font-bold text-blue-700">SKOR TOTAL</p>
                    <p class="text-2xl font-bold text-blue-600">${toFixedNumber(data.skor_total)}%</p>
                </div>
            `;

            rows.innerHTML = data.detail.map((row) => `
                <tr class="border-b border-slate-100">
                    <td data-label="No" class="px-4 py-3">${Number(row.no)}</td>
                    <td data-label="Kegiatan" class="px-4 py-3">${escapeHtml(row.kegiatan)}</td>
                    <td data-label="Sesuai" class="px-4 py-3 text-center">${Number(row.sesuai)}</td>
                    <td data-label="Arahan" class="px-4 py-3 text-center">${Number(row.arahan)}</td>
                    <td data-label="Telat" class="px-4 py-3 text-center">${Number(row.telat)}</td>
                    <td data-label="Tidak" class="px-4 py-3 text-center">${Number(row.tidak)}</td>
                    <td data-label="Libur" class="px-4 py-3 text-center">${Number(row.libur)}</td>
                    <td data-label="Skor" class="px-4 py-3 text-center font-semibold">${toFixedNumber(row.skor)}%</td>
                </tr>
            `).join('');
        } catch (error) {
            showMessage(message, error.message, false);
        }
    };

    childSelect.addEventListener('change', load);
    startInput.addEventListener('change', load);
    endInput.addEventListener('change', load);
    document.getElementById('refreshSummary')?.addEventListener('click', load);

    load();
}
