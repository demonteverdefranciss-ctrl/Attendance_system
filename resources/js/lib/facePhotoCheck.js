import { detectFaces } from '@/lib/picoFace';

export const MIN_IMAGE_PX = 200;
export const MIN_FACE_HEIGHT_RATIO = 0.22;
export const MIN_FACE_WIDTH_RATIO = 0.16;

function cascadeUrl(assetBase) {
    const base = String(assetBase || '').replace(/\/$/, '');
    return `${base}/face-detection/facefinder`;
}

export async function inspectFacePhoto(file, assetBase = '') {
    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
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
        if (faces.length === 0) {
            return {
                ok: false,
                reason: 'NO_FACE',
                message:
                    "No face was detected. Upload a clear, front-facing close-up of the student only — not an object, screenshot, or full-body photo.",
            };
        }
        if (faces.length > 1) {
            return {
                ok: false,
                reason: 'MULTIPLE_FACES',
                message: 'More than one face was found. Recapture a photo with only the student.',
            };
        }
        const size = faces[0].size;
        if (size < height * MIN_FACE_HEIGHT_RATIO || size < width * MIN_FACE_WIDTH_RATIO) {
            return {
                ok: false,
                reason: 'NOT_CLOSE_UP',
                message:
                    "This looks like a full-body or distant photo. Recapture a close-up of the student's face filling most of the frame.",
            };
        }
        return { ok: true, reason: 'OK', message: 'Face is valid.' };
    } catch {
        bitmap.close?.();
        return {
            ok: false,
            reason: 'PHOTO_VALIDATION_UNAVAILABLE',
            message: 'The system could not check this photo. Recapture a close-up of the student\'s face and try again.',
        };
    }
}
