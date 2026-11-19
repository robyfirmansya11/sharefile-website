import { ref } from 'vue';
import { csrfToken, responseError } from '@/lib/shareApi';

export type UploadItem = { name: string; progress: number; state: 'waiting' | 'uploading' | 'saving' | 'done' | 'failed'; error?: string };

export function useShareUpload() {
    const items = ref<UploadItem[]>([]);
    const running = ref(false);

    function send(file: File, path: string, item: UploadItem): Promise<void> {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/share/upload');
            xhr.setRequestHeader('Accept', 'application/json');
            const token = csrfToken();
            if (token) xhr.setRequestHeader('X-XSRF-TOKEN', token);
            xhr.timeout = 300000;
            item.state = 'uploading';
            xhr.upload.onprogress = event => {
                if (event.lengthComputable) item.progress = Math.round(event.loaded / event.total * 100);
                if (item.progress === 100) item.state = 'saving';
            };
            xhr.onload = () => {
                let data;
                try { data = JSON.parse(xhr.responseText); } catch { data = null; }
                if (xhr.status >= 200 && xhr.status < 300 && data?.message) resolve();
                else reject(new Error(responseError(xhr.status, data)));
            };
            xhr.onerror = () => reject(new Error('Koneksi terputus. Muat ulang folder sebelum mencoba lagi; file mungkin sudah tersimpan.'));
            xhr.ontimeout = () => reject(new Error('Waktu upload habis. Muat ulang folder untuk memeriksa apakah file sudah tersimpan.'));
            const body = new FormData();
            body.append('path', path);
            body.append('files[]', file);
            xhr.send(body);
        });
    }

    async function upload(files: File[], path: string) {
        if (running.value) return;
        if (files.length > 20) throw new Error('Pilih maksimal 20 file dalam satu antrean.');
        items.value = files.map(file => ({ name: file.name, progress: 0, state: 'waiting' }));
        running.value = true;
        try {
            for (const [index, file] of files.entries()) {
                const item = items.value[index];
                try {
                    if (file.size > 25 * 1024 * 1024) throw new Error('File melebihi batas 25 MB.');
                    await send(file, path, item);
                    item.progress = 100;
                    item.state = 'done';
                } catch (error) {
                    item.state = 'failed';
                    item.error = (error as Error).message;
                }
            }
        } finally { running.value = false; }
    }

    return { items, running, upload };
}
