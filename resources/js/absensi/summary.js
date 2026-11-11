import { apiGet, escapeHtml, hideMessage, showMessage, toFixedNumber } from './api';

const STATUS_META = [
    ['sesuai', 'SESUAI', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    ['arahan', 'ARAHAN', 'border-amber-200 bg-amber-50 text-amber-700'],
    ['telat', 'TELAT', 'border-orange-200 bg-orange-50 text-orange-700'],
    ['tidak', 'TIDAK', 'border-rose-200 bg-rose-50 text-rose-700'],
    ['libur', 'LIBUR', 'border-slate-200 bg-slate-50 text-slate-600'],
];

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

            const total = STATUS_META.slice(0, 4).reduce((sum, [key]) => sum + Number(data.status[key] || 0), 0);

            cards.innerHTML = STATUS_META.map(([key, title, color]) => `
                <div class="rounded-lg border py-4 ${color}">
                    <p class="text-xs font-bold">${title}</p>
                    <p class="text-2xl font-bold">${Number(data.status[key] || 0)}</p>
                    <p class="text-xs">${total ? ((Number(data.status[key] || 0) / total) * 100).toFixed(1) : '0.0'}%</p>
                </div>
            `).join('') + `
                <div class="rounded-lg border border-blue-200 bg-blue-50 py-4 text-blue-700">
                    <p class="text-xs font-bold">SKOR TOTAL</p>
                    <p class="text-2xl font-bold">${toFixedNumber(data.skor_total)}%</p>
                </div>
            `;

            rows.innerHTML = data.detail.map((row) => `
                <tr>
                    <td class="px-4 py-3">${Number(row.no)}</td>
                    <td class="px-4 py-3">${escapeHtml(row.kegiatan)}</td>
                    <td class="px-4 py-3 text-center">${Number(row.sesuai)}</td>
                    <td class="px-4 py-3 text-center">${Number(row.arahan)}</td>
                    <td class="px-4 py-3 text-center">${Number(row.telat)}</td>
                    <td class="px-4 py-3 text-center">${Number(row.tidak)}</td>
                    <td class="px-4 py-3 text-center">${Number(row.libur)}</td>
                    <td class="px-4 py-3 text-center font-semibold">${toFixedNumber(row.skor)}%</td>
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
