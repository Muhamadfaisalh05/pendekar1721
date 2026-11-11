import { initAnak } from './absensi/anak';
import { initDashboard } from './absensi/dashboard';
import { initInput } from './absensi/input';
import { initPengaturan } from './absensi/pengaturan';
import { initRapor } from './absensi/rapor';
import { initSummary } from './absensi/summary';

const initializers = {
    dashboard: initDashboard,
    input: initInput,
    anak: initAnak,
    summary: initSummary,
    rapor: initRapor,
    pengaturan: initPengaturan,
};

document.addEventListener('DOMContentLoaded', () => {
    const page = document.body.dataset.absensiPage;
    const init = initializers[page];

    if (typeof init === 'function') {
        init();
    }
});
