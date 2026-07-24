"""Generate capstone objectives progress PDF."""

from pathlib import Path

from fpdf import FPDF


OUT = Path(__file__).resolve().parents[1] / "docs" / "Objectives_Progress_Report.pdf"

ROWS = [
    ("1", "Centralized attendance system with facial recognition, RBAC, monitoring, reporting, and parent notifications", "Substantially achieved", "95%"),
    ("2", "Cross-platform system (teacher recognition app, parent/teacher mobile, web dashboards)", "Substantially achieved", "90%"),
    ("3", "Automated attendance tracking (time-in/out, status, absenteeism, reports, real-time parent notifications)", "Substantially achieved", "93%"),
    ("4", "Secure management of attendance and facial data (RA 10173)", "Substantially achieved", "85%"),
    ("5", "ISO 25010 evaluation by teachers, parents, and IT experts", "Prepared, not yet conducted", "25%"),
]


class PDF(FPDF):
    def header(self):
        self.set_font("Helvetica", "B", 11)
        self.set_text_color(30, 58, 138)
        self.cell(0, 8, "Pamantasan ng Cabuyao - College of Computing Studies", align="C")
        self.ln(6)
        self.set_draw_color(29, 78, 216)
        self.set_line_width(0.4)
        self.line(15, self.get_y(), 195, self.get_y())
        self.ln(8)

    def footer(self):
        self.set_y(-15)
        self.set_font("Helvetica", "", 8)
        self.set_text_color(100, 100, 100)
        self.cell(0, 10, f"Page {self.page_no()}/{{nb}}  |  Report date: July 24, 2026", align="C")


def multi_cell_row(pdf: PDF, heights, cols, widths, aligns):
    x_start = pdf.get_x()
    y_start = pdf.get_y()
    row_h = max(heights)
    x = x_start
    for i, (text, w, a) in enumerate(zip(cols, widths, aligns)):
        pdf.rect(x, y_start, w, row_h)
        pdf.set_xy(x + 1.5, y_start + 1.5)
        pdf.multi_cell(w - 3, 5, text, align=a)
        x += w
    pdf.set_xy(x_start, y_start + row_h)


def measure_height(pdf: PDF, text: str, width: float) -> float:
    lines = pdf.multi_cell(width - 3, 5, text, dry_run=True, output="LINES")
    return max(12, len(lines) * 5 + 3)


def main():
    pdf = PDF(orientation="P", unit="mm", format="A4")
    pdf.alias_nb_pages()
    pdf.set_auto_page_break(auto=True, margin=20)
    pdf.add_page()

    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(15, 23, 42)
    pdf.multi_cell(0, 7, "CAPSTONE PROJECT PROGRESS REPORT", align="C")
    pdf.ln(2)

    pdf.set_font("Helvetica", "", 10)
    pdf.set_text_color(51, 65, 85)
    pdf.multi_cell(
        0,
        5,
        "Cross-Platform Student Attendance Management System with Facial Recognition, "
        "Parent Notification, and Analytics for Grade 6 Pupils of Bigaa Elementary School",
        align="C",
    )
    pdf.ln(4)

    pdf.set_font("Helvetica", "", 10)
    pdf.set_text_color(30, 41, 59)
    pdf.cell(0, 6, "Development methodology: Agile (iterative sprints)")
    pdf.ln(6)
    pdf.set_font("Helvetica", "B", 11)
    pdf.set_text_color(29, 78, 216)
    pdf.cell(0, 7, "Overall development progress: 80%")
    pdf.ln(10)

    pdf.set_font("Helvetica", "B", 12)
    pdf.set_text_color(15, 23, 42)
    pdf.cell(0, 7, "II. Progress Relative to the Specific Objectives")
    pdf.ln(8)

    # Table header
    widths = [12, 105, 48, 20]
    aligns = ["C", "L", "L", "C"]
    headers = ["Obj.", "Specific Objective", "Status", "%"]

    pdf.set_fill_color(29, 78, 216)
    pdf.set_text_color(255, 255, 255)
    pdf.set_font("Helvetica", "B", 9)
    x = pdf.get_x()
    y = pdf.get_y()
    for h, w in zip(headers, widths):
        pdf.set_xy(x, y)
        pdf.cell(w, 9, h, border=1, align="C", fill=True)
        x += w
    pdf.ln(9)

    pdf.set_text_color(30, 41, 59)
    pdf.set_font("Helvetica", "", 9)

    for obj, desc, status, pct in ROWS:
        cols = [obj, desc, status, pct]
        heights = [measure_height(pdf, c, w) for c, w in zip(cols, widths)]
        if pdf.get_y() + max(heights) > 270:
            pdf.add_page()
            pdf.set_font("Helvetica", "B", 9)
            pdf.set_fill_color(29, 78, 216)
            pdf.set_text_color(255, 255, 255)
            x = pdf.get_x()
            y = pdf.get_y()
            for h, w in zip(headers, widths):
                pdf.set_xy(x, y)
                pdf.cell(w, 9, h, border=1, align="C", fill=True)
                x += w
            pdf.ln(9)
            pdf.set_text_color(30, 41, 59)
            pdf.set_font("Helvetica", "", 9)
        multi_cell_row(pdf, heights, cols, widths, aligns)

    # Overall row
    pdf.set_font("Helvetica", "B", 9)
    pdf.set_fill_color(239, 246, 255)
    pdf.set_text_color(15, 23, 42)
    overall = ["-", "Overall (equal-weight average of Objectives 1-5)", "-", "80%"]
    heights = [measure_height(pdf, c, w) for c, w in zip(overall, widths)]
    y0 = pdf.get_y()
    x = pdf.l_margin
    for i, (text, w, a) in enumerate(zip(overall, widths, aligns)):
        pdf.set_fill_color(239, 246, 255)
        pdf.rect(x, y0, w, max(heights), style="DF")
        pdf.set_xy(x + 1.5, y0 + 1.5)
        pdf.multi_cell(w - 3, 5, text, align=a)
        x += w
    pdf.set_y(y0 + max(heights) + 6)

    pdf.set_font("Helvetica", "B", 11)
    pdf.cell(0, 7, "Scoring notes")
    pdf.ln(7)
    pdf.set_font("Helvetica", "", 9)
    pdf.set_text_color(51, 65, 85)
    pdf.multi_cell(
        0,
        5,
        "Each objective is estimated from implemented deliverables versus remaining gaps. "
        "Overall completion is the simple average of Objectives 1-5 (equal weight). "
            "Objective 5 remains low until UAT and ISO 25010 questionnaires are conducted and tabulated.",
    )
    # ASCII-only body text for core PDF fonts
    pdf.ln(6)

    pdf.set_font("Helvetica", "B", 11)
    pdf.set_text_color(15, 23, 42)
    pdf.cell(0, 7, "Completed vs remaining (summary)")
    pdf.ln(7)

    details = [
        (
            "Objective 1 (95%)",
            "Done: Web RBAC, LBPH face pipeline, analytics/reports, FCM notifications, enrollment workflow.\n"
            "Remaining: Optional ArcFace upgrade; stable production hosting.",
        ),
        (
            "Objective 2 (90%)",
            "Done: Admin/teacher/parent web; Python recognition + Tapo camera; Flutter parent + teacher app and APK.\n"
            "Remaining: In-app FCM push; optional recognition upgrades; release polish.",
        ),
        (
            "Objective 3 (93%)",
            "Done: Session open/close, face + manual marking, time-out, absent fill, CSV/PDF reports, parent notify.\n"
            "Remaining: Stable hosting; richer absenteeism analytics (backlog).",
        ),
        (
            "Objective 4 (85%)",
            "Done: Consent gates, audit logs, encrypted embeddings, retention purge, RA 10173 checklist.\n"
            "Remaining: Formal PIA document; recognition-node dataset purge automation.",
        ),
        (
            "Objective 5 (25%)",
            "Done: UAT plan and ISO 25010 evaluation instrument drafted.\n"
            "Remaining: Conduct UAT, collect questionnaires, tabulate results for Chapter 4 / defense.",
        ),
    ]

    for title, body in details:
        pdf.set_font("Helvetica", "B", 9)
        pdf.set_text_color(29, 78, 216)
        pdf.cell(0, 6, title)
        pdf.ln(5)
        pdf.set_font("Helvetica", "", 9)
        pdf.set_text_color(51, 65, 85)
        pdf.multi_cell(0, 4.5, body)
        pdf.ln(3)

    pdf.ln(2)
    pdf.set_font("Helvetica", "B", 11)
    pdf.set_text_color(15, 23, 42)
    pdf.cell(0, 7, "Recommended next actions")
    pdf.ln(7)

    actions = [
        ("1", "Restore / stabilize Railway hosting (or switch to local XAMPP)", "Obj. 1-3 demo readiness"),
        ("2", "Conduct UAT with teachers and parents", "Raises Obj. 5"),
        ("3", "Collect and tabulate ISO 25010 questionnaires", "Completes Obj. 5"),
        ("4", "Optional: FCM in Flutter + formal PIA write-up", "Obj. 2 & 4 polish"),
    ]

    pdf.set_font("Helvetica", "B", 9)
    pdf.set_fill_color(29, 78, 216)
    pdf.set_text_color(255, 255, 255)
    aw = [12, 118, 55]
    for h, w in zip(["#", "Action", "Improves"], aw):
        pdf.cell(w, 8, h, border=1, align="C", fill=True)
    pdf.ln(8)
    pdf.set_font("Helvetica", "", 9)
    pdf.set_text_color(30, 41, 59)
    for num, action, improves in actions:
        # simple single-line cells; wrap action if needed
        y = pdf.get_y()
        h_action = measure_height(pdf, action, aw[1])
        h_imp = measure_height(pdf, improves, aw[2])
        h = max(h_action, h_imp, 10)
        x = pdf.l_margin
        cells = [num, action, improves]
        aligns_a = ["C", "L", "L"]
        for text, w, a in zip(cells, aw, aligns_a):
            pdf.rect(x, y, w, h)
            pdf.set_xy(x + 1.5, y + 1.5)
            pdf.multi_cell(w - 3, 4.5, text, align=a)
            x += w
        pdf.set_xy(pdf.l_margin, y + h)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(OUT))
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    main()
