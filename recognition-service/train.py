"""
Train the selected recognition engine from dataset/<student_id>/*.png.

  RECOGNITION_ENGINE=lbph     (default) writes models/lbph.yml
  RECOGNITION_ENGINE=arcface  writes models/arcface_gallery.npz

The student's backend id is used as the label. Re-run after enrolling new students.
"""
import json
import os

import cv2
import numpy as np

import config


def train_lbph():
    if not os.path.isdir(config.DATASET_DIR):
        print("No dataset directory found. Run enroll.py first.")
        return

    images, labels = [], []
    student_dirs = [d for d in os.listdir(config.DATASET_DIR)
                    if os.path.isdir(os.path.join(config.DATASET_DIR, d)) and d.isdigit()]

    for sid in student_dirs:
        folder = os.path.join(config.DATASET_DIR, sid)
        for name in os.listdir(folder):
            img = cv2.imread(os.path.join(folder, name), cv2.IMREAD_GRAYSCALE)
            if img is None:
                continue
            images.append(cv2.resize(img, config.FACE_SIZE))
            labels.append(int(sid))

    if not images:
        print("No training images found. Enroll students first.")
        return

    recognizer = cv2.face.LBPHFaceRecognizer_create()
    recognizer.train(images, np.array(labels))

    os.makedirs(config.MODEL_DIR, exist_ok=True)
    recognizer.write(config.MODEL_PATH)

    trained = sorted(set(labels))
    with open(config.LABELS_PATH, "w") as fh:
        json.dump({"engine": "lbph", "student_ids": trained, "samples": len(images)}, fh, indent=2)

    print(f"Trained on {len(images)} images for {len(trained)} student(s): {trained}")
    print(f"Model saved to {config.MODEL_PATH}")


def main():
    print(f"Recognition engine: {config.RECOGNITION_ENGINE}")
    if config.RECOGNITION_ENGINE == "arcface":
        from engine_arcface import build_gallery

        build_gallery()
        return

    train_lbph()


if __name__ == "__main__":
    main()
