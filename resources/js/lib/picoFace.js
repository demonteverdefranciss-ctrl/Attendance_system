/* MIT pico.js cascade runtime (nenadmarkus/picojs), adapted as an ES module. */

export function unpackCascade(bytes) {
    const dview = new DataView(new ArrayBuffer(4));
    let p = 8;
    dview.setUint8(0, bytes[p + 0]);
    dview.setUint8(1, bytes[p + 1]);
    dview.setUint8(2, bytes[p + 2]);
    dview.setUint8(3, bytes[p + 3]);
    const tdepth = dview.getInt32(0, true);
    p += 4;
    dview.setUint8(0, bytes[p + 0]);
    dview.setUint8(1, bytes[p + 1]);
    dview.setUint8(2, bytes[p + 2]);
    dview.setUint8(3, bytes[p + 3]);
    const ntrees = dview.getInt32(0, true);
    p += 4;

    const tcodesLs = [];
    const tpredsLs = [];
    const threshLs = [];
    const pow2 = 2 ** tdepth;

    for (let t = 0; t < ntrees; t += 1) {
        tcodesLs.push(0, 0, 0, 0);
        for (let i = 0; i < 4 * pow2 - 4; i += 1) {
            tcodesLs.push(bytes[p + i]);
        }
        p += 4 * pow2 - 4;
        for (let i = 0; i < pow2; i += 1) {
            dview.setUint8(0, bytes[p + 0]);
            dview.setUint8(1, bytes[p + 1]);
            dview.setUint8(2, bytes[p + 2]);
            dview.setUint8(3, bytes[p + 3]);
            tpredsLs.push(dview.getFloat32(0, true));
            p += 4;
        }
        dview.setUint8(0, bytes[p + 0]);
        dview.setUint8(1, bytes[p + 1]);
        dview.setUint8(2, bytes[p + 2]);
        dview.setUint8(3, bytes[p + 3]);
        threshLs.push(dview.getFloat32(0, true));
        p += 4;
    }

    const tcodes = new Int8Array(tcodesLs);
    const tpreds = new Float32Array(tpredsLs);
    const thresh = new Float32Array(threshLs);

    return function classifyRegion(r, c, s, pixels, ldim) {
        r *= 256;
        c *= 256;
        let root = 0;
        let o = 0.0;
        for (let i = 0; i < ntrees; i += 1) {
            let idx = 1;
            for (let j = 0; j < tdepth; j += 1) {
                idx =
                    2 * idx +
                    (pixels[((r + tcodes[root + 4 * idx + 0] * s) >> 8) * ldim + ((c + tcodes[root + 4 * idx + 1] * s) >> 8)] <=
                        pixels[((r + tcodes[root + 4 * idx + 2] * s) >> 8) * ldim + ((c + tcodes[root + 4 * idx + 3] * s) >> 8)]);
            }
            o += tpreds[pow2 * i + idx - pow2];
            if (o <= thresh[i]) {
                return -1;
            }
            root += 4 * pow2;
        }
        return o - thresh[ntrees - 1];
    };
}

export function runCascade(image, classifyRegion, params) {
    const { pixels, nrows, ncols, ldim } = image;
    const { shiftfactor, minsize, maxsize, scalefactor } = params;
    let scale = minsize;
    const detections = [];

    while (scale <= maxsize) {
        const step = Math.max(shiftfactor * scale, 1) >> 0;
        const offset = (scale / 2 + 1) >> 0;
        for (let r = offset; r <= nrows - offset; r += step) {
            for (let c = offset; c <= ncols - offset; c += step) {
                const q = classifyRegion(r, c, scale, pixels, ldim);
                if (q > 0.0) {
                    detections.push([r, c, scale, q]);
                }
            }
        }
        scale *= scalefactor;
    }

    return detections;
}

export function clusterDetections(dets, iouThreshold) {
    const sorted = dets.slice().sort((a, b) => b[3] - a[3]);
    const assignments = new Array(sorted.length).fill(0);
    const clusters = [];

    const iou = (a, b) => {
        const overr = Math.max(0, Math.min(a[0] + a[2] / 2, b[0] + b[2] / 2) - Math.max(a[0] - a[2] / 2, b[0] - b[2] / 2));
        const overc = Math.max(0, Math.min(a[1] + a[2] / 2, b[1] + b[2] / 2) - Math.max(a[1] - a[2] / 2, b[1] - b[2] / 2));
        return (overr * overc) / (a[2] * a[2] + b[2] * b[2] - overr * overc);
    };

    for (let i = 0; i < sorted.length; i += 1) {
        if (assignments[i] !== 0) {
            continue;
        }
        let r = 0.0;
        let c = 0.0;
        let s = 0.0;
        let q = 0.0;
        let n = 0;
        for (let j = i; j < sorted.length; j += 1) {
            if (iou(sorted[i], sorted[j]) > iouThreshold) {
                assignments[j] = 1;
                r += sorted[j][0];
                c += sorted[j][1];
                s += sorted[j][2];
                q += sorted[j][3];
                n += 1;
            }
        }
        clusters.push([r / n, c / n, s / n, q]);
    }

    return clusters;
}

let classifyPromise;

function loadClassifier(cascadeUrl) {
    if (!classifyPromise) {
        classifyPromise = fetch(cascadeUrl)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Face-detection cascade is missing.');
                }
                return response.arrayBuffer();
            })
            .then((buffer) => unpackCascade(new Int8Array(buffer)))
            .catch((error) => {
                classifyPromise = null;
                throw error;
            });
    }
    return classifyPromise;
}

export function selectPrimaryFace(faces) {
    if (!faces.length) {
        return { status: 'none', face: null };
    }
    const sorted = [...faces].sort((a, b) => b.score - a.score || b.size - a.size);
    const best = sorted[0];
    const rival = sorted.slice(1).find(
        (other) => other.size >= best.size * 0.6 && other.score >= Math.max(10, best.score * 0.4),
    );
    if (rival) {
        return { status: 'multiple', face: best };
    }
    return { status: 'one', face: best };
}

export async function detectFaces(bitmap, cascadeUrl) {
    const classify = await loadClassifier(cascadeUrl);
    const maxSide = 256;
    const scale = Math.max(bitmap.width, bitmap.height) / maxSide;
    const workW = Math.max(1, Math.round(bitmap.width / Math.max(scale, 1)));
    const workH = Math.max(1, Math.round(bitmap.height / Math.max(scale, 1)));
    const canvas = document.createElement('canvas');
    canvas.width = workW;
    canvas.height = workH;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(bitmap, 0, 0, workW, workH);
    const rgba = ctx.getImageData(0, 0, workW, workH).data;
    const pixels = new Uint8Array(workW * workH);
    for (let r = 0; r < workH; r += 1) {
        for (let c = 0; c < workW; c += 1) {
            const i = (r * workW + c) * 4;
            pixels[r * workW + c] = (2 * rgba[i] + 7 * rgba[i + 1] + rgba[i + 2]) / 10;
        }
    }

    const dets = runCascade(
        { pixels, nrows: workH, ncols: workW, ldim: workW },
        classify,
        {
            shiftfactor: 0.12,
            minsize: Math.max(24, Math.round(Math.min(workW, workH) * 0.08)),
            maxsize: Math.min(workW, workH),
            scalefactor: 1.2,
        },
    );
    const backScale = Math.max(scale, 1);
    return clusterDetections(dets, 0.2)
        .filter((cluster) => cluster[3] >= 5)
        .map((cluster) => ({
            size: Math.max(1, Math.round(cluster[2] * backScale)),
            score: cluster[3],
        }));
}
