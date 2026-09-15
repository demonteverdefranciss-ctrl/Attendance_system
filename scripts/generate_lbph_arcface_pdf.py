"""Generate LBPH vs ArcFace explanation PDF for the attendance system."""

from datetime import date
from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parents[1]
OUT_DOCS = ROOT / "docs" / "LBPH_vs_ArcFace_Explanation.pdf"
OUT_DESKTOP = Path.home() / "Desktop" / "LBPH_vs_ArcFace_Explanation.pdf"


class PDF(FPDF):
    def header(self):
        self.set_font("Helvetica", "B", 11)
        self.set_text_color(30, 58, 138)
        self.cell(0, 8, "Bigaa Elementary School Attendance System", align="C")
        self.ln(5)
        self.set_font("Helvetica", "", 9)
        self.set_text_color(75, 85, 99)
        self.cell(0, 6, "Facial Recognition Algorithms: LBPH and ArcFace", align="C")
        self.ln(4)
        self.set_draw_color(29, 78, 216)
        self.set_line_width(0.4)
        self.line(15, self.get_y(), 195, self.get_y())
        self.ln(8)

    def footer(self):
        self.set_y(-15)
        self.set_font("Helvetica", "", 8)
        self.set_text_color(100, 100, 100)
        self.cell(
            0,
            10,
            f"Page {self.page_no()}/{{nb}}  |  Capstone reference  |  {date.today().isoformat()}",
            align="C",
        )


def section_title(pdf: PDF, text: str):
    pdf.set_font("Helvetica", "B", 13)
    pdf.set_text_color(30, 58, 138)
    pdf.multi_cell(0, 8, text)
    pdf.ln(1)


def body(pdf: PDF, text: str):
    pdf.set_font("Helvetica", "", 11)
    pdf.set_text_color(31, 41, 55)
    pdf.multi_cell(0, 6, text)
    pdf.ln(2)


def bullet(pdf: PDF, text: str):
    pdf.set_font("Helvetica", "", 11)
    pdf.set_text_color(31, 41, 55)
    pdf.set_x(20)
    pdf.multi_cell(0, 6, f"- {text}")


def build() -> Path:
    pdf = PDF()
    pdf.alias_nb_pages()
    pdf.set_auto_page_break(auto=True, margin=18)
    pdf.add_page()

    section_title(pdf, "1. Overview")
    body(
        pdf,
        "The attendance system uses a school PC camera to recognize enrolled students "
        "and record attendance when a session is open. Two matchers are supported: "
        "LBPH (Local Binary Patterns Histogram) and ArcFace via OpenCV SFace with YuNet "
        "face detection. Both compare a live face to enrolled student photos, but they "
        "describe and compare faces differently.",
    )

    section_title(pdf, "2. Shared pipeline")
    body(pdf, "Regardless of algorithm, the flow is:")
    for item in [
        "The camera captures a video frame.",
        "A face is detected in the frame.",
        "The face is compared to enrolled students.",
        "If the match is strong enough for several frames, and an attendance session is open, attendance is posted to the backend.",
    ]:
        bullet(pdf, item)
    pdf.ln(2)

    section_title(pdf, "3. How LBPH works")
    body(
        pdf,
        "LBPH describes a face using local texture patterns. The face is converted to "
        "grayscale. Each pixel is compared with nearby pixels to form a binary pattern, "
        "and those patterns become a histogram (a texture signature) for the student. "
        "A live face is matched by distance: lower distance means a better match.",
    )
    body(
        pdf,
        "In this project, LBPH accepts a match when the distance is at or below the "
        "configured threshold (about 70-75). The trained model is stored as models/lbph.yml.",
    )
    body(pdf, "Strengths:")
    for item in [
        "Simple and fast on a school PC.",
        "Small model and easy to run offline.",
        "Useful as a classic OpenCV baseline.",
    ]:
        bullet(pdf, item)
    pdf.ln(1)
    body(pdf, "Weaknesses:")
    for item in [
        "Sensitive to lighting changes.",
        "Sensitive to pose, angle, and expression.",
        "Must be retrained when new students are added.",
        "More likely to confuse similar-looking students in uncontrolled conditions.",
    ]:
        bullet(pdf, item)
    pdf.ln(2)

    section_title(pdf, "4. How ArcFace works (OpenCV SFace + YuNet)")
    body(
        pdf,
        "ArcFace in this system means: detect the face, convert it into a numerical "
        "fingerprint (embedding), then compare that fingerprint to enrolled students. "
        "YuNet detects faces in the frame. SFace, trained with ArcFace-style learning, "
        "converts each face into an embedding vector. Similar faces produce similar "
        "vectors; different people produce different vectors.",
    )
    body(
        pdf,
        "During training (train.py), student photos become embeddings saved in "
        "models/arcface_gallery.npz. During live recognition, cosine similarity is used. "
        "Higher similarity means a stronger match. The system accepts matches around "
        "0.36 and above (ARCFACE_THRESHOLD), then still requires consecutive frames, "
        "an open session, and a cooldown before recording attendance.",
    )
    body(pdf, "Strengths:")
    for item in [
        "More stable under lighting changes.",
        "Better with slight pose and angle variation.",
        "Better separation between different people.",
        "Closer to modern face recognition practice.",
    ]:
        bullet(pdf, item)
    pdf.ln(1)
    body(pdf, "Weaknesses:")
    for item in [
        "Needs ONNX model files (YuNet and SFace).",
        "Slightly heavier than LBPH.",
        "Still needs clear enrollment photos and a gallery rebuild when adding students.",
    ]:
        bullet(pdf, item)
    pdf.ln(2)

    section_title(pdf, "5. Comparison")
    # Simple table
    pdf.set_font("Helvetica", "B", 10)
    pdf.set_fill_color(239, 246, 255)
    pdf.set_text_color(30, 58, 138)
    col_w = [42, 68, 68]
    headers = ["Aspect", "LBPH", "ArcFace (SFace)"]
    for i, h in enumerate(headers):
        pdf.cell(col_w[i], 8, h, border=1, fill=True)
    pdf.ln()
    pdf.set_font("Helvetica", "", 9)
    pdf.set_text_color(31, 41, 55)
    rows = [
        ("Method", "Handcrafted texture patterns", "Deep face embeddings"),
        ("Comparison", "Histogram distance", "Cosine similarity"),
        ("Lighting", "Weak", "Stronger"),
        ("Pose / angle", "Weak", "Stronger"),
        ("Accuracy", "Good in controlled setup", "Better overall"),
        ("Speed", "Very fast", "Fast enough for school use"),
        ("Model file", "lbph.yml", "arcface_gallery.npz"),
        ("Best for", "Simple lab baseline", "Real hallway attendance"),
    ]
    for row in rows:
        y0 = pdf.get_y()
        x0 = pdf.get_x()
        heights = []
        for i, text in enumerate(row):
            pdf.set_xy(x0 + sum(col_w[:i]), y0)
            # measure
            lines = pdf.multi_cell(col_w[i], 5, text, border=0, dry_run=True, output="LINES")
            heights.append(5 * len(lines))
        row_h = max(heights)
        if y0 + row_h > pdf.page_break_trigger:
            pdf.add_page()
            y0 = pdf.get_y()
        for i, text in enumerate(row):
            pdf.set_xy(x0 + sum(col_w[:i]), y0)
            pdf.multi_cell(col_w[i], 5, text, border=1)
        pdf.set_y(y0 + row_h)
    pdf.ln(4)

    section_title(pdf, "6. Why ArcFace is better for this project")
    body(
        pdf,
        "A school hallway is not a controlled lab. Lighting changes, students move, "
        "and faces are not always frontal. ArcFace is the better production matcher because:",
    )
    for item in [
        "It learns identity features instead of relying only on local pixel texture.",
        "It handles lighting and pose changes more reliably with a Tapo camera.",
        "It reduces false matches between different students.",
        "It reflects modern face recognition practice, which strengthens the capstone.",
        "For day-to-day attendance use, it is more reliable than LBPH.",
    ]:
        bullet(pdf, item)
    pdf.ln(2)
    body(
        pdf,
        "LBPH remains useful as a baseline algorithm. ArcFace is the preferred default "
        "matcher for real school attendance recognition.",
    )

    section_title(pdf, "7. One-sentence summary")
    body(
        pdf,
        "LBPH compares face textures; ArcFace compares learned face embeddings. "
        "ArcFace is better because those embeddings stay more stable when lighting, "
        "angle, and camera conditions change.",
    )

    OUT_DOCS.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(OUT_DOCS))
    try:
        pdf.output(str(OUT_DESKTOP))
    except OSError:
        pass
    return OUT_DOCS


if __name__ == "__main__":
    path = build()
    print(f"Wrote {path}")
    desktop = Path.home() / "Desktop" / "LBPH_vs_ArcFace_Explanation.pdf"
    if desktop.exists():
        print(f"Also copied to {desktop}")
