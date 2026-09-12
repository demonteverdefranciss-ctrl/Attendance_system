import { detectFaces, selectPrimaryFace } from '@/lib/picoFace';

export const MIN_IMAGE_PX = 200;
export const MIN_FACE_HEIGHT_RATIO = 0.14;
export const MIN_FACE_WIDTH_RATIO = 0.1;
const PREPARE_MAX_SIDE = 1280;
const PREPARE_JPEG_QUALITY = 0.82;

function cascadeUrl(assetBase) {
    const base = String(assetBase || '').replace(/\/$/, '');
    return `${base}/face-detection/facefinder`;
}

async function bitmapFromFile(file) {
    try {
        return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return createImageBitmap(file);
    }
}

/**
 * Bake EXIF orientation and shrink phone photos so they fit PHP's upload cap.
 */
export async function prepareFacePhoto(file) {
    let bitmap;
    try {
        bitmap = await bitmapFromFile(file);
    } catch {
        return file;
    }

    const scale = Math.min(1, PREPARE_MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        bitmap.close?.();
        return file;
    }
    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => {
        canvas.toBlob(resolve, 'image/jpeg', PREPARE_JPEG_QUALITY);
    });
    if (!blob) {
        return file;
    }

    const baseName = String(file.name || 'face').replace(/\.[^.]+$/, '');
    return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

export async function inspectFacePhoto(file, assetBase = '') {
    let bitmap;
    try {
        bitmap = await bitmapFromFile(file);
    } catch {
        return {
            ok: false,
            reason: 'INVALID_IMAGE',
            message: 'This file could not be read as a photo. Upload a JPEG or PNG.',
        };
    }

    const width = bitmap.width;
    const height = bitmap.height;
    if (width < MIN_IMAGE_PX || height < MIN_IMAGE_PX) {
        bitmap.close?.();
        return {
            ok: false,
            reason: 'TOO_SMALL',
            message: "The photo is too small. Recapture a clearer, closer photo of the student's face.",
        };
    }

    try {
        const faces = await detectFaces(bitmap, cascadeUrl(assetBase));
        bitmap.close?.();
        const picked = selectPrimaryFace(faces);
        if (picked.status === 'none') {
            return {
                ok: false,
                reason: 'NO_FACE',
                message:
                    "No face was detected. Upload a clear, front-facing photo of the student — not an object, screenshot, or full-body photo.",
            };
        }
        if (picked.status === 'multiple') {
            return {
                ok: false,
                reason: 'MULTIPLE_FACES',
                message: 'More than one face was found. Recapture a photo with only the student.',
            };
        }
        const size = picked.face.size;
        if (size < height * MIN_FACE_HEIGHT_RATIO || size < width * MIN_FACE_WIDTH_RATIO) {
            return {
                ok: false,
                reason: 'NOT_CLOSE_UP',
                message:
                    "This looks like a full-body or distant photo. Recapture a closer photo of the student's face.",
            };
        }
        return { ok: true, reason: 'OK', message: 'Face is valid.' };
    } catch {
        bitmap.close?.();
        // Let the server decide if the browser cascade failed to load.
        return { ok: true, reason: 'SKIPPED', message: 'Face will be checked when you submit.' };
    }
}
