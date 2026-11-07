/**
 * Shrinks a phone photo before upload: saves mobile data and time.
 * Drawing to a canvas also drops EXIF (including GPS); the server strips it again anyway.
 */
export async function compressPhoto(file, { maxEdge = 1600, quality = 0.85 } = {}) {
    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        // Format the browser cannot decode (e.g. some HEIC): send as is and let the server decide.
        return file;
    }

    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height));
    const width = Math.round(bitmap.width * scale);
    const height = Math.round(bitmap.height * scale);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    if (!blob) {
        return file;
    }
    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
}
