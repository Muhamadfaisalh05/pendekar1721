import { apiGet, apiPost, escapeHtml } from './api';

const SAVE_ICON = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8M7 3v5h8M5.25 3h11.5A2.25 2.25 0 0 1 19 5.25v13.5A2.25 2.25 0 0 1 16.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" /></svg>';

const UPDATE_ICON = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487Z" /></svg>';

const EDIT_ICON = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487Z" /></svg>';

const DELETE_ICON = '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12.003.397c.343-.059.686-.115 1.03-.166m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>';

const statusClass = (status) => status === 'Aktif'
    ? 'bg-green-100 text-green-700'
    : status === 'Lulus' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700';

export function initAnak() {
    const form = document.getElementById('formAnak');
    const tbody = document.getElementById('tbodyAnak');
    const mobileCards = document.getElementById('mobileCards');
    const submitButton = document.getElementById('btnSubmit');
    const btnBatal = document.getElementById('btnBatal');
    const btnTambah = document.getElementById('btnTambah');
    const idField = document.getElementById('anakId');
    const idManualField = document.getElementById('idAnak');
    const modal = document.getElementById('modalAnak');
    const modalTitle = document.getElementById('modalAnakTitle');

    if (!form || !tbody || !mobileCards || !modal) {
        return;
    }

    const fields = {
        nama: document.getElementById('nama'),
        jenisKelamin: document.getElementById('jenisKelamin'),
        asalDaerah: document.getElementById('asalDaerah'),
        agama: document.getElementById('agama'),
        umur: document.getElementById('umur'),
        angkatan: document.getElementById('angkatan'),
        status: document.getElementById('status'),
    };

    const openModal = () => {
        modal.classList.remove('hidden');
        document.body.classList.add('modal-open');
        fields.nama.focus();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open');
        btnTambah.focus();
    };

    const resetForm = () => {
        form.reset();
        idField.value = '';
        idManualField.value = '';
        submitButton.innerHTML = `${SAVE_ICON} Simpan`;
        modalTitle.textContent = 'Input Anak';
        closeModal();
    };

    const renderTable = (rows) => {
        if (!Array.isArray(rows) || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-slate-500">
                        Belum ada data anak.
                    </td>
                </tr>
            `;
            mobileCards.innerHTML = '<p class="px-4 py-8 text-center text-slate-500">Belum ada data anak.</p>';
            return;
        }

        tbody.innerHTML = rows.map((item) => `
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">${escapeHtml(item.id || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(item.nama || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(item.jenis_kelamin || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(item.asal_daerah || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(item.agama || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(item.umur || '-')}</td>
                <td class="px-4 py-3">${escapeHtml(item.angkatan || '-')}</td>
                <td class="px-4 py-3">
                    <span class="rounded px-2 py-1 text-xs font-medium ${statusClass(item.status)}">
                        ${escapeHtml(item.status || 'Aktif')}
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex justify-center gap-2">
                        <button type="button" data-action="edit" data-id="${escapeHtml(item.id)}"
                            aria-label="Edit ${escapeHtml(item.nama)}"
                            class="text-xs font-medium text-blue-600 hover:text-blue-800">${EDIT_ICON}</button>
                        <button type="button" data-action="delete" data-id="${escapeHtml(item.id)}"
                            aria-label="Hapus ${escapeHtml(item.nama)}"
                            class="text-xs font-medium text-red-600 hover:text-red-800">${DELETE_ICON}</button>
                    </div>
                </td>
            </tr>
        `).join('');

        mobileCards.innerHTML = rows.map((item) => `
            <article class="child-mobile-card">
                <div class="child-mobile-card-heading">
                    <div class="min-w-0">
                        <h4 class="child-mobile-name">${escapeHtml(item.nama || '-')}</h4>
                        <p class="child-mobile-id">${escapeHtml(item.id || '-')}</p>
                    </div>
                    <div class="child-mobile-heading-actions">
                        <span class="rounded px-2 py-1 text-xs font-medium ${statusClass(item.status)}">${escapeHtml(item.status || 'Aktif')}</span>
                        <button type="button" class="child-mobile-toggle" aria-expanded="false"
                            aria-label="Tampilkan detail ${escapeHtml(item.nama || '')}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </div>
                </div>
                <dl class="child-mobile-details">
                    <div><dt>Jenis kelamin</dt><dd>${escapeHtml(item.jenis_kelamin || '-')}</dd></div>
                    <div><dt>Asal daerah</dt><dd>${escapeHtml(item.asal_daerah || '-')}</dd></div>
                    <div><dt>Agama</dt><dd>${escapeHtml(item.agama || '-')}</dd></div>
                    <div><dt>Umur</dt><dd>${escapeHtml(item.umur || '-')} tahun</dd></div>
                    <div><dt>Angkatan</dt><dd>${escapeHtml(item.angkatan || '-')}</dd></div>
                </dl>
                <div class="child-mobile-actions">
                    <button type="button" data-action="edit" data-id="${escapeHtml(item.id)}" class="child-mobile-edit">
                        ${EDIT_ICON} Edit
                    </button>
                    <button type="button" data-action="delete" data-id="${escapeHtml(item.id)}"
                        class="child-mobile-delete">
                        ${DELETE_ICON} Hapus
                    </button>
                </div>
            </article>
        `).join('');
    };

    const loadAnak = async () => {
        try {
            renderTable(await apiGet('anak'));
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-red-500">${escapeHtml(error.message)}</td></tr>`;
            mobileCards.textContent = error.message;
        }
    };


    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = {
            id: idField.value || idManualField.value || '',
            nama: fields.nama.value,
            jenis_kelamin: fields.jenisKelamin.value,
            asal_daerah: fields.asalDaerah.value,
            agama: fields.agama.value,
            umur: Number(fields.umur.value),
            angkatan: fields.angkatan.value,
            status: fields.status.value,
        };

        const action = idField.value ? 'anak_update' : 'anak_store';

        try {
            const result = await apiPost(action, payload);
            alert(result.message || 'Berhasil');
            resetForm();
            await loadAnak();
        } catch (error) {
            alert(error.message || 'Gagal menyimpan data anak');
        }
    });

    const handleRowButton = async (event) => {
        const button = event.target.closest('button');
        if (!button) {
            return;
        }

        const { action, id } = button.dataset;
        if (!id) {
            return;
        }

        if (action === 'edit') {
            try {
                const data = await apiGet('anak_detail', { id });
                if (!data || data.success === false) {
                    throw new Error(data.message || 'Data tidak ditemukan');
                }

                idField.value = data.id;
                idManualField.value = data.id;
                fields.nama.value = data.nama || '';
                fields.jenisKelamin.value = data.jenis_kelamin || '';
                fields.asalDaerah.value = data.asal_daerah || '';
                fields.agama.value = data.agama || '';
                fields.umur.value = data.umur || 0;
                fields.angkatan.value = data.angkatan || '';
                fields.status.value = data.status || 'Aktif';
                submitButton.innerHTML = `${UPDATE_ICON} Update`;
                modalTitle.textContent = 'Edit Data Anak';
                openModal();
            } catch (error) {
                alert(error.message || 'Data tidak dapat dibuka');
            }

            return;
        }

        if (action === 'delete') {
            const confirmed = confirm(`Apakah Anda yakin ingin menghapus data anak ${id}?`);
            if (!confirmed) {
                return;
            }

            try {
                const result = await apiPost('anak_delete', { id });
                alert(result.message || 'Data berhasil dihapus');
                await loadAnak();
            } catch (error) {
                alert(error.message || 'Gagal menghapus data');
            }
        }
    };

    btnBatal.addEventListener('click', resetForm);
    btnTambah.addEventListener('click', () => {
        form.reset();
        idField.value = '';
        idManualField.value = '';
        submitButton.innerHTML = `${SAVE_ICON} Simpan`;
        modalTitle.textContent = 'Input Anak';
        openModal();
    });
    modal.addEventListener('click', (event) => {
        if (event.target.closest('[data-close-modal]')) {
            resetForm();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            resetForm();
        }
    });
    tbody.addEventListener('click', handleRowButton);
    mobileCards.addEventListener('click', handleRowButton);
    mobileCards.addEventListener('click', (event) => {
        const toggle = event.target.closest('.child-mobile-toggle');
        if (!toggle) {
            return;
        }

        const card = toggle.closest('.child-mobile-card');
        const expanded = card.classList.toggle('child-mobile-expanded');
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', `${expanded ? 'Sembunyikan' : 'Tampilkan'} detail ${card.querySelector('.child-mobile-name').textContent}`);
        toggle.querySelector('svg')?.classList.toggle('rotate-180', expanded);
    });

    loadAnak();
}

