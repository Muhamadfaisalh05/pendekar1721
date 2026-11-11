const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

const apiUrl = () => meta('absensi-api');
const csrfToken = () => meta('csrf-token');

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function buildUrl(action, params = {}) {
    const url = new URL(apiUrl(), window.location.origin);
    url.searchParams.set('action', action);

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, value);
        }
    });

    return url.toString();
}

export async function apiGet(action, params = {}) {
    const response = await fetch(buildUrl(action, params), {
        headers: { Accept: 'application/json' },
    });
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data?.message || 'Gagal memuat data.');
    }

    return data;
}

export async function apiPost(action, payload = {}) {
    const response = await fetch(buildUrl(action), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!response.ok || data?.success === false) {
        throw new Error(data?.message || 'Gagal menyimpan data.');
    }

    return data;
}

export function showMessage(element, message, success = true) {
    if (!element) {
        return;
    }

    element.textContent = message;
    element.className = success
        ? 'mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800'
        : 'mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800';
}

export function hideMessage(element) {
    if (element) {
        element.className = 'hidden';
        element.textContent = '';
    }
}

export function toFixedNumber(value, digits = 1) {
    return Number(value ?? 0).toFixed(digits);
}
