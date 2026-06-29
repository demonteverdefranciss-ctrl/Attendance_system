"""
Capture face samples for a student.

Usage:
  python enroll.py <student_id>                 # capture from the webcam / RTSP source
  python enroll.py <student_id> --images <dir>  # import from a folder of photos
"""
import argparse
import os

import cv2

import config

_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")


def detect_largest_face(gray):
    faces = _cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
    if len(faces) == 0:
        return None
    x, y, w, h = max(faces, key=lambda f: f[2] * f[3])
    return gray[y:y + h, x:x + w]


def save_face(student_dir, idx, face):
    cv2.imwrite(os.path.join(student_dir, f"{idx:03d}.png"), cv2.resize(face, config.FACE_SIZE))


def from_webcam(student_dir, target):
    cap = cv2.VideoCapture(config.resolved_video_source())
    if not cap.isOpened():
        print("ERROR: cannot open video source", config.VIDEO_SOURCE)
        return 0

    count = 0
    print(f"Capturing {target} samples. Look at the camera; press q to stop early.")
    while count < target:
        ok, frame = cap.read()
        if not ok:
            break
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        face = detect_largest_face(gray)
        if face is not None:
            save_face(student_dir, count, face)
            count += 1
        if config.SHOW_WINDOW:
            cv2.putText(frame, f"{count}/{target}", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
            cv2.imshow("Enroll", frame)
            if cv2.waitKey(1) & 0xFF == ord("q"):
                break
    cap.release()
    cv2.destroyAllWindows()
    return count


def from_images(student_dir, src):
    count = 0
    for name in sorted(os.listdir(src)):
        img = cv2.imread(os.path.join(src, name), cv2.IMREAD_GRAYSCALE)
        if img is None:
            continue
        face = detect_largest_face(img)
        if face is not None:
            save_face(student_dir, count, face)
            count += 1
    return count


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("student_id", type=int, help="The backend student id to enroll")
    parser.add_argument("--images", help="Folder of photos to import instead of using the webcam")
    args = parser.parse_args()

    student_dir = os.path.join(config.DATASET_DIR, str(args.student_id))
    os.makedirs(student_dir, exist_ok=True)

    n = from_images(student_dir, args.images) if args.images else from_webcam(student_dir, config.SAMPLES_PER_STUDENT)

    print(f"Saved {n} face samples to {student_dir}")
    print("Next step: python train.py")


if __name__ == "__main__":
    main()
