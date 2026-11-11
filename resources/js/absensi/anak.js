import { apiGet, apiPost, escapeHtml, hideMessage, showMessage } from './api';

export function initAnak() {
    const form = document.getElementById('formAnak');
    const rowsBody = document.getElementById('anakRows');

    if (!form || !rowsBody) {
        return;
    }

    const message = document.getElementById('anakMessage');
    const fields = {
        id: document.getElementById('anakId'),
        nama: document.getElementById('nama'),
        jenis_kelamin: document.getElementById('jenis_kelamin'),
        asal_daerah: document.getElementById('asal_daerah'),
        agama: document.getElementById('agama'),
        umur: document.getElementById('umur'),
        angkatan: document.getElementById('angkatan'),
        status: document.getElementById('status'),
    };

    let editingId = '';

    const resetForm = () => {
        editingId = '';
        form.reset();
        fields.id.value = '';
        fields.id.disabled = false;
        fields.nama.focus();
    };

    const render = (list) => {
        rowsBody.innerHTML = list.map((item) => `
            <tr class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-slate-600">${escapeHtml(item.id)}</td>
                <td class="px-4 py-3 font-medium text-slate-700">${escapeHtml(item.nama)}</td>
                <td class="px-4 py-3">${escapeHtml(item.jenis_kelamin)}</td>
                <td class="px-4 py-3">${escapeHtml(item.asal_daerah)}</td>
                <td class="px-4 py-3">${escapeHtml(item.agama)}</td>
                <td class="px-4 py-3 text-center">${escapeHtml(item.umur)}</td>
                <td class="px-4 py-3">${escapeHtml(item.angkatan)}</td>
                <td class="px-4 py-3">${escapeHtml(item.status)}</td>
                <td class="px-4 py-3">
                    <button type="button" data-action="edit" data-id="${escapeHtml(item.id)}"
                        class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit</button>
                    <button type="button" data-action="delete" data-id="${escapeHtml(item.id)}"
                        class="ml-3 text-xs font-medium text-rose-600 hover:text-rose-800">Hapus</button>
                </td>
            </tr>
        `).join('');
    };

    const load = async () => {
        try {
            render(await apiGet('anak'));
        } catch (error) {
            showMessage(message, error.message, false);
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideMessage(message);

        const payload = {
            nama: fields.nama.value.trim(),
            jenis_kelamin: fields.jenis_kelamin.value,
            asal_daerah: fields.asal_daerah.value.trim(),
            agama: fields.agama.value.trim(),
            umur: Number(fields.umur.value),
            angkatan: fields.angkatan.value.trim(),
            status: fields.status.value,
        };

        try {
            if (editingId) {
                const result = await apiPost('anak_update', { id: editingId, ...payload });
                showMessage(message, result.message, true);
            } else {
                const id = fields.id.value.trim();
                const result = await apiPost('anak_store', id ? { id, ...payload } : payload);
                showMessage(message, result.message, true);
            }

            resetForm();
            await load();
        } catch (error) {
            showMessage(message, error.message, false);
        }
    });

    rowsBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }

        const id = button.dataset.id;

        if (button.dataset.action === 'edit') {
            try {
                const item = await apiGet('anak_detail', { id });
                if (!item || item.success === false) {
                    throw new Error(item?.message || 'Data anak tidak ditemukan.');
                }

                editingId = item.id;
                fields.id.value = item.id;
                fields.id.disabled = true;
                fields.nama.value = item.nama;
                fields.jenis_kelamin.value = item.jenis_kelamin;
                fields.asal_daerah.value = item.asal_daerah;
                fields.agama.value = item.agama;
                fields.umur.value = item.umur;
                fields.angkatan.value = item.angkatan;
                fields.status.value = item.status;
                fields.nama.focus();
                hideMessage(message);
            } catch (error) {
                showMessage(message, error.message, false);
            }

            return;
        }

        if (!window.confirm(`Hapus data anak ${id}? Riwayat evaluasinya juga akan dihapus.`)) {
            return;
        }

        try {
            const result = await apiPost(`anak_delete&id=${encodeURIComponent(id)}`, {});
            showMessage(message, result.message, true);
            resetForm();
            await load();
        } catch (error) {
            showMessage(message, error.message, false);
        }
    });

    document.getElementById('resetFormAnak')?.addEventListener('click', resetForm);

    load();
}
