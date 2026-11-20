import { apiGet, apiPost, hideMessage, showMessage } from './api';

const WEEKDAYS = ['1', '2', '3', '4', '5'];

export function initPengaturan() {
    const message = document.getElementById('pengaturanMessage');
    const rows = [...document.querySelectorAll('[data-schedule]')];

    if (!message) {
        return;
    }

    const showRuleOptions = (row) => {
        const rule = row.querySelector('[data-rule]').value;
        row.querySelector('[data-days]').classList.toggle('hidden', rule !== 'hari_tertentu');
        row.querySelector('[data-ranges]').classList.toggle('hidden', rule !== 'multi_rentang');
    };

    const addRange = (list, range = { mulai: '', selesai: '' }) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'schedule-range-row flex items-center gap-2 text-xs';
        wrapper.innerHTML = `
            <input type="date" data-from aria-label="Tanggal mulai rentang aktif" value="${range.mulai ?? ''}"
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-slate-600">
            <span class="text-slate-400">s.d.</span>
            <input type="date" data-to aria-label="Tanggal selesai rentang aktif" value="${range.selesai ?? ''}"
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-slate-600">
            <button type="button" data-remove-range
                class="px-2 text-xs text-red-600 hover:text-red-800" title="Hapus rentang">Hapus</button>
        `;
        wrapper.querySelector('[data-remove-range]').addEventListener('click', () => wrapper.remove());
        list.appendChild(wrapper);
    };

    const loadSettings = async () => {
        hideMessage(message);

        try {
            const settings = await apiGet('settings');
            const weekdays = WEEKDAYS;

            rows.forEach((row) => {
                const id = row.dataset.schedule;
                const setting = settings.jadwal?.[id] || { aturan: 'setiap_hari', hari: weekdays, rentang: [] };

                row.querySelector('[data-rule]').value = setting.aturan;
                row.querySelectorAll('[data-day]').forEach((input) => {
                    input.checked = (setting.hari || []).map(String).includes(input.dataset.day);
                });

                const list = row.querySelector('[data-range-list]');
                list.innerHTML = '';
                (setting.rentang || []).forEach((range) => addRange(list, range));
                if (!list.children.length) {
                    addRange(list);
                }

                showRuleOptions(row);
            });

            document.querySelectorAll('[data-cohort]').forEach((row) => {
                const saved = settings.angkatan?.find((item) => item.angkatan === row.dataset.cohort);
                row.querySelector('[data-end-date]').value = saved?.tanggal_selesai || '';
            });
        } catch (error) {
            showMessage(message, error.message, false);
        }
    };

    rows.forEach((row) => {
        row.querySelector('[data-rule]').addEventListener('change', () => showRuleOptions(row));
        row.querySelector('[data-add-range]').addEventListener('click', () => {
            addRange(row.querySelector('[data-range-list]'));
        });
    });

    document.getElementById('simpanJadwal')?.addEventListener('click', async (event) => {
        const jadwal = {};

        rows.forEach((row) => {
            jadwal[row.dataset.schedule] = {
                aturan: row.querySelector('[data-rule]').value,
                hari: [...row.querySelectorAll('[data-day]:checked')].map((input) => input.dataset.day),
                rentang: [...row.querySelector('[data-range-list]').children]
                    .map((range) => ({
                        mulai: range.querySelector('[data-from]').value,
                        selesai: range.querySelector('[data-to]').value,
                    }))
                    .filter((range) => range.mulai && range.selesai),
            };
        });

        event.currentTarget.disabled = true;

        try {
            const result = await apiPost('settings_save', { jadwal });
            showMessage(message, result.message, true);
        } catch (error) {
            showMessage(message, error.message, false);
        } finally {
            event.currentTarget.disabled = false;
        }
    });

    document.getElementById('simpanPembinaan')?.addEventListener('click', async (event) => {
        const angkatan = {};

        document.querySelectorAll('[data-cohort]').forEach((row) => {
            angkatan[row.dataset.cohort] = row.querySelector('[data-end-date]').value;
        });

        event.currentTarget.disabled = true;

        try {
            const result = await apiPost('settings_save', { angkatan });
            showMessage(message, result.message, true);
        } catch (error) {
            showMessage(message, error.message, false);
        } finally {
            event.currentTarget.disabled = false;
        }
    });

    loadSettings();
}
