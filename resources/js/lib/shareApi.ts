export function csrfToken(): string | null {
    const value = document.cookie.split('; ').find(cookie => cookie.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
    return value ? decodeURIComponent(value) : null;
}

export function responseError(status: number, data?: { message?: string; errors?: Record<string, string[]> }): string {
    if (status === 401 || status === 419) return 'Sesi berakhir. Muat ulang halaman dan masuk kembali.';
    if (status === 413) return data?.message || 'Ukuran file melampaui batas server. Pilih file yang lebih kecil.';
    if (status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar sebelum mencoba kembali.';
    return data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || 'Permintaan gagal. Muat ulang untuk memeriksa hasil terakhir.';
}

export async function shareApi(url: string, init: RequestInit = {}) {
    const token = csrfToken();
    const response = await fetch(url, { ...init, headers: { Accept: 'application/json', ...(token ? { 'X-XSRF-TOKEN': token } : {}), ...init.headers } });
    const data = await response.json().catch(() => null);
    if (!response.ok || !data) throw new Error(responseError(response.status, data));
    return data;
}
