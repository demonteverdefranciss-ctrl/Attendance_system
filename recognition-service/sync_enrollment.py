"""
Import teacher-approved parent photos into the local LBPH dataset.

Usage:
  python sync_enrollment.py          # download + enroll + train
  python sync_enrollment.py --dry-run

After a parent uploads photos and a teacher approves them on the website,
run this on the school PC to pull the images from the cloud API and rebuild
the recognition model.
"""
import argparse
import os
import shutil
import subprocess
import sys

from api_client import (
    download_biometric_photo,
    get_approved_biometric_submissions,
    mark_biometric_submission_synced,
)
import config


def import_submission(item, dry_run=False):
    student_id = item["student_id"]
    submission_id = item["submission_id"]
    student_dir = os.path.join(config.DATASET_DIR, str(student_id))
    import_dir = os.path.join(student_dir, "_parent_import")

    print(f"Student {student_id} ({item.get('student_name', '?')}): {len(item['photos'])} photo(s)")

    if dry_run:
        return True

    if os.path.isdir(student_dir):
        shutil.rmtree(student_dir)
    os.makedirs(import_dir, exist_ok=True)

    for index, photo in enumerate(item["photos"]):
        resp = download_biometric_photo(photo["id"])
        if resp.status_code != 200:
            print(f"  [ERR] download photo {photo['id']}: HTTP {resp.status_code}")
            return False

        ext = os.path.splitext(photo.get("original_name") or "photo.jpg")[1] or ".jpg"
        path = os.path.join(import_dir, f"{index:03d}{ext}")
        with open(path, "wb") as fh:
            fh.write(resp.content)

    # Reuse enroll.py image import logic.
    result = subprocess.run(
        [sys.executable, "enroll.py", str(student_id), "--images", import_dir],
        cwd=os.path.dirname(os.path.abspath(__file__)),
        check=False,
    )
    shutil.rmtree(import_dir, ignore_errors=True)

    if result.returncode != 0:
        print(f"  [ERR] enroll.py failed for student {student_id}")
        return False

    sync_resp = mark_biometric_submission_synced(submission_id)
    if sync_resp.status_code not in (200, 201):
        print(f"  [WARN] could not mark synced: HTTP {sync_resp.status_code} {sync_resp.text[:120]}")
    else:
        print(f"  [OK] imported and marked submission {submission_id} synced")

    return True


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--dry-run", action="store_true", help="List approved submissions only")
    args = parser.parse_args()

    resp = get_approved_biometric_submissions()
    if resp.status_code != 200:
        print(f"ERROR: HTTP {resp.status_code} {resp.text[:200]}")
        return

    items = resp.json().get("data", [])
    if not items:
        print("No approved parent photo submissions waiting to sync.")
        return

    print(f"Found {len(items)} approved submission(s).")
    ok = 0
    for item in items:
        if import_submission(item, dry_run=args.dry_run):
            ok += 1

    if args.dry_run:
        print("Dry run complete.")
        return

    if ok:
        print("Training model...")
        train = subprocess.run([sys.executable, "train.py"], cwd=os.path.dirname(os.path.abspath(__file__)))
        if train.returncode != 0:
            print("ERROR: train.py failed")
            return
        print("Done. Run recognize.py to start live recognition.")
    else:
        print("No submissions were imported.")


if __name__ == "__main__":
    main()
