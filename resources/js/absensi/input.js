import { apiGet, apiPost, hideMessage, showMessage } from './api';

const LABELS = { sesuai: 'Sesuai', arahan: 'Arahan', telat: 'Telat', tidak: 'Tidak', libur: 'Libur' };

/**
 * Port logika halaman absensi.php native: muat jadwal + nilai, disable kegiatan
 * yang tidak dijadwalkan, simpan per kegiatan, dan tandai sakit/izin (libur).
 */
export function initInput() {
    const dateInput = document.getElementById('tanggalEvaluasi');
    const message = document.getElementById('pesanEvaluasi');

    if (!dateInput || !message) {
        return;
    }

    const activities = [...document.querySelectorAll('[data-activity]')];

    const setValue = (card, value) => {
        card.dataset.value = value ?? '';
        const label = card.querySelector('[data-value-label]');
        label.textContent = value ? LABELS[value] ?? value : '';
        label.classList.toggle('hidden', !value);

        card.querySelectorAll('.eval-button').forEach((button) => {
            const active = button.dataset.value === value;
            button.classList.toggle('bg-blue-600', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-blue-600', active);
        });
    };

    const isScheduled = (schedules, activityId, dateValue) => {
        const schedule = schedules[activityId] || { aturan: 'setiap_hari', hari: [], rentang: [] };
        const weekday = String(new Date(`${dateValue}T12:00:00`).getDay() || 7);

        if (schedule.aturan === 'hari_kerja') {
            return ['1', '2', '3', '4', '5'].includes(weekday);
        }

        if (schedule.aturan === 'hari_tertentu') {
            return (schedule.hari || []).map(String).includes(weekday);
        }

        if (schedule.aturan === 'multi_rentang') {
            return (schedule.rentang || []).some((range) => range.mulai && range.selesai
                && dateValue >= range.mulai && dateValue <= range.selesai);
        }

        return true;
    };

    const load = async () => {
        hideMessage(message);

        try {
            const [rows, settings] = await Promise.all([
                apiGet('evaluasi', { tanggal: dateInput.value }),
                apiGet('settings'),
            ]);

            activities.forEach((activity) => {
                activity.querySelectorAll('[data-child]').forEach((card) => setValue(card, ''));

                const scheduled = isScheduled(settings.jadwal || {}, activity.dataset.activity, dateInput.value);
                activity.dataset.scheduled = scheduled ? '1' : '0';
                activity.querySelectorAll('.eval-button, [data-bulk], [data-save]').forEach((button) => {
                    button.disabled = !scheduled;
                });

                const status = document.getElementById(`status-${activity.dataset.activity}`);
                status.textContent = scheduled ? 'Belum disimpan' : 'Tidak dijadwalkan';
            });

            rows.forEach((row) => {
                const activity = document.querySelector(`[data-activity="${row.kegiatan_id}"]`);
                const card = activity?.querySelector(`[data-child="${CSS.escape(row.anak_id)}"]`);
                if (card) {
                    setValue(card, row.nilai);
                }
            });

            activities.forEach((activity) => {
                const cards = [...activity.querySelectorAll('[data-child]')];
                const allSaved = cards.length > 0 && cards.every((card) => card.dataset.value);
                const status = document.getElementById(`status-${activity.dataset.activity}`);

                status.textContent = allSaved
                    ? 'Tersimpan'
                    : activity.dataset.scheduled === '1' ? 'Belum disimpan' : 'Tidak dijadwalkan';
            });
        } catch (error) {
            showMessage(message, error.message, false);
        }
    };

    activities.forEach((activity) => {
        activity.querySelector('[data-accordion]')?.addEventListener('click', () => {
            activity.querySelector('[data-accordion-content]')?.classList.toggle('hidden');
            activity.querySelector('[data-accordion-icon]')?.classList.toggle('rotate-180');
        });

        activity.addEventListener('click', async (event) => {
            const bulk = event.target.closest('[data-bulk]');
            if (bulk) {
                activity.querySelectorAll('[data-child]').forEach((card) => setValue(card, bulk.dataset.bulk));
                document.getElementById(`status-${activity.dataset.activity}`).textContent = 'Belum disimpan';
                return;
            }

            const valueButton = event.target.closest('.eval-button');
            if (valueButton) {
                setValue(valueButton.closest('[data-child]'), valueButton.dataset.value);
                document.getElementById(`status-${activity.dataset.activity}`).textContent = 'Belum disimpan';
                return;
            }

            const saveButton = event.target.closest('[data-save]');
            if (!saveButton) {
                return;
            }

            const items = [...activity.querySelectorAll('[data-child]')]
                .filter((card) => card.dataset.value)
                .map((card) => ({
                    anak_id: card.dataset.child,
                    kegiatan_id: Number(activity.dataset.activity),
                    tanggal: dateInput.value,
                    nilai: card.dataset.value,
                }));

            if (!items.length) {
                showMessage(message, 'Pilih nilai untuk setidaknya satu anak sebelum menyimpan.', false);
                return;
            }

            saveButton.disabled = true;

            try {
                const result = await apiPost('save_evaluasi_batch', { items });
                document.getElementById(`status-${activity.dataset.activity}`).textContent = 'Tersimpan';
                showMessage(message, result.message, true);
            } catch (error) {
                showMessage(message, error.message, false);
            } finally {
                saveButton.disabled = false;
            }
        });
    });

    document.getElementById('simpanLibur')?.addEventListener('click', async (event) => {
        const selected = [...document.querySelectorAll('[data-leave-child]:checked')];

        if (!selected.length) {
            showMessage(message, 'Pilih anak yang sakit atau izin terlebih dahulu.', false);
            return;
        }

        const items = [];
        selected.forEach((checkbox) => {
            activities.forEach((activity) => {
                items.push({
                    anak_id: checkbox.dataset.leaveChild,
                    kegiatan_id: Number(activity.dataset.activity),
                    tanggal: dateInput.value,
                    nilai: 'libur',
                });
            });
        });

        event.currentTarget.disabled = true;

        try {
            await apiPost('save_evaluasi_batch', { items });
            await load();
            showMessage(message, `Sakit/izin disimpan untuk ${selected.length} anak pada seluruh kegiatan tanggal ini.`, true);
        } catch (error) {
            showMessage(message, error.message, false);
        } finally {
            event.currentTarget.disabled = false;
        }
    });

    dateInput.addEventListener('change', () => {
        document.querySelectorAll('[data-leave-child]').forEach((checkbox) => {
            checkbox.checked = false;
        });
        load();
    });

    load();
}
