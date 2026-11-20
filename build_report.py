"""
build_report.py — Generates LAPORAN MAGANG - KHOLIS (REVISED).docx
Internship Final Report for Kholis Kamaluddin Wahib, Horizon University Indonesia
Backend Developer Intern @ UPTD PPSGBR Lembang | PENDEKAR1721 Project
Run: python build_report.py
Requires: pip install python-docx
"""

from docx import Document
from docx.shared import Pt, Inches, RGBColor, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.enum.section import WD_SECTION_START, WD_ORIENT
from docx.enum.table import WD_ALIGN_VERTICAL, WD_TABLE_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
from copy import deepcopy
import os

OUT_PATH = r"c:\laragon\www\Dinsos\pendekar1721\LAPORAN MAGANG - KHOLIS (REVISED).docx"

# ─── HELPERS ────────────────────────────────────────────────────────────────

def set_margins(section, top=1.0, bottom=1.0, left=1.0, right=1.5):
    section.top_margin    = Inches(top)
    section.bottom_margin = Inches(bottom)
    section.left_margin   = Inches(left)
    section.right_margin  = Inches(right)

def set_font(run, name="Times New Roman", size_pt=12, bold=False, color=None):
    run.font.name = name
    run.font.size = Pt(size_pt)
    run.font.bold = bold
    if color:
        run.font.color.rgb = color
    # Force complex/east-asian script font too
    rPr = run._r.get_or_add_rPr()
    for tag in [qn("w:rFonts")]:
        el = rPr.find(tag)
        if el is None:
            el = OxmlElement(tag)
            rPr.insert(0, el)
        el.set(qn("w:ascii"), name)
        el.set(qn("w:hAnsi"), name)
        el.set(qn("w:cs"),    name)
        el.set(qn("w:eastAsia"), name)

def para_spacing(para, before=0, after=6, line=1.5):
    pf = para.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after  = Pt(after)
    if line == 1.0:
        pf.line_spacing_rule = WD_LINE_SPACING.SINGLE
    elif line == 1.5:
        pf.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    elif line == 2.0:
        pf.line_spacing_rule = WD_LINE_SPACING.DOUBLE
    pf.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY

def add_heading(doc, text, level=1, numbering=None):
    """level 1=CHAPTER, 2=sub, 3=sub-sub"""
    sizes = {1: 16, 2: 14, 3: 12}
    size = sizes.get(level, 12)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT if level > 1 else WD_ALIGN_PARAGRAPH.CENTER
    pf = p.paragraph_format
    pf.space_before = Pt(12)
    pf.space_after  = Pt(6)
    pf.line_spacing_rule = WD_LINE_SPACING.ONE_POINT_FIVE
    label = (numbering + " " if numbering else "") + (text.upper() if level == 1 else text)
    r = p.add_run(label)
    set_font(r, size_pt=size, bold=True)
    return p

def add_body(doc, text, indent_left=0, indent_first=0.5):
    p = doc.add_paragraph()
    para_spacing(p)
    pf = p.paragraph_format
    pf.left_indent      = Inches(indent_left)
    pf.first_line_indent = Inches(indent_first)
    r = p.add_run(text)
    set_font(r, size_pt=12)
    return p

def add_blank(doc):
    p = doc.add_paragraph()
    para_spacing(p, before=0, after=0, line=1.0)
    return p

def page_break(doc):
    p = doc.add_paragraph()
    r = p.add_run()
    r.add_break(docx_break_type="page")
    return p

def add_page_number_bottom_right(section):
    """Add Arabic page numbers at bottom-right of footer."""
    footer = section.footer
    footer.is_linked_to_previous = False
    p = footer.paragraphs[0] if footer.paragraphs else footer.add_paragraph()
    p.clear()
    p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    r = p.add_run()
    set_font(r, size_pt=11)
    fldChar1 = OxmlElement("w:fldChar")
    fldChar1.set(qn("w:fldCharType"), "begin")
    instrText = OxmlElement("w:instrText")
    instrText.set(qn("xml:space"), "preserve")
    instrText.text = "PAGE"
    fldChar2 = OxmlElement("w:fldChar")
    fldChar2.set(qn("w:fldCharType"), "end")
    r._r.append(fldChar1)
    r._r.append(instrText)
    r._r.append(fldChar2)

def make_table_header_row(table, row_idx, texts, font_size=10):
    row = table.rows[row_idx]
    for i, text in enumerate(texts):
        if i >= len(row.cells):
            break
        cell = row.cells[i]
        cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
        # Shade header
        tc = cell._tc
        tcPr = tc.get_or_add_tcPr()
        shd = OxmlElement("w:shd")
        shd.set(qn("w:val"), "clear")
        shd.set(qn("w:color"), "auto")
        shd.set(qn("w:fill"), "1F3864")
        tcPr.append(shd)
        p = cell.paragraphs[0] if cell.paragraphs else cell.add_paragraph()
        p.clear()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = p.add_run(text)
        set_font(r, size_pt=font_size, bold=True, color=RGBColor(0xFF, 0xFF, 0xFF))

def add_toc(doc):
    """Insert a Word TOC field that auto-updates."""
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("TABLE OF CONTENTS")
    set_font(r, size_pt=14, bold=True)
    para_spacing(p, before=0, after=12, line=1.0)

    # Insert TOC field
    p2 = doc.add_paragraph()
    r2 = p2.add_run()
    fldChar = OxmlElement("w:fldChar")
    fldChar.set(qn("w:fldCharType"), "begin")
    r2._r.append(fldChar)
    instrText = OxmlElement("w:instrText")
    instrText.set(qn("xml:space"), "preserve")
    instrText.text = ' TOC \\o "1-3" \\h \\z \\u '
    r2._r.append(instrText)
    fldChar2 = OxmlElement("w:fldChar")
    fldChar2.set(qn("w:fldCharType"), "separate")
    r2._r.append(fldChar2)
    # Placeholder text
    r3 = p2.add_run("[Right-click and select 'Update Field' to refresh Table of Contents]")
    set_font(r3, size_pt=10)
    fldChar3 = OxmlElement("w:fldChar")
    fldChar3.set(qn("w:fldCharType"), "end")
    r2._r.append(fldChar3)

# ─── DOCUMENT BUILD ──────────────────────────────────────────────────────────

doc = Document()

# Default styles
style = doc.styles["Normal"]
style.font.name = "Times New Roman"
style.font.size = Pt(12)

# ════════════════════════════════════════════════════════════════════════════
# SECTION 1: COVER PAGE
# ════════════════════════════════════════════════════════════════════════════
sec1 = doc.sections[0]
set_margins(sec1)
sec1.different_first_page_header_footer = False
# Remove page number from cover
sec1.header.is_linked_to_previous = False
sec1.footer.is_linked_to_previous = False

DARK_BLUE = RGBColor(0x1F, 0x38, 0x64)
GOLD      = RGBColor(0xC9, 0xA9, 0x00)
WHITE     = RGBColor(0xFF, 0xFF, 0xFF)

def cover_para(doc, text, size=12, bold=False, color=None, align=WD_ALIGN_PARAGRAPH.CENTER, space_after=4):
    p = doc.add_paragraph()
    p.alignment = align
    pf = p.paragraph_format
    pf.space_before = Pt(0)
    pf.space_after  = Pt(space_after)
    pf.line_spacing_rule = WD_LINE_SPACING.SINGLE
    r = p.add_run(text)
    set_font(r, size_pt=size, bold=bold, color=color)
    return p

# Dark blue background via section shading would require complex XML;
# instead we use dark-blue colored text on white, with the cover being
# styled using large bold dark-blue text as per handbook.

cover_para(doc, "", size=12, space_after=12)
cover_para(doc, "INTERNSHIP FINAL REPORT", size=20, bold=True, color=DARK_BLUE, space_after=6)
cover_para(doc, "", size=10, space_after=4)

# Logo placeholder
cover_para(doc, "[INSERT LOGO: Horizon University Indonesia]", size=11, color=DARK_BLUE, space_after=6)
cover_para(doc, "", size=10, space_after=4)

cover_para(doc, "PENDEKAR1721", size=16, bold=True, color=DARK_BLUE, space_after=4)
cover_para(doc, "User Profile Management and Social Services Administration System", size=13, color=DARK_BLUE, space_after=4)
cover_para(doc, "for UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR)", size=13, bold=True, color=DARK_BLUE, space_after=4)
cover_para(doc, "Lembang, West Java — Dinas Sosial Provinsi Jawa Barat", size=12, color=DARK_BLUE, space_after=12)

cover_para(doc, "Internship Period: August 2026 – December 2026", size=12, bold=True, color=DARK_BLUE, space_after=12)

cover_para(doc, "Submitted by:", size=12, color=DARK_BLUE, space_after=4)
cover_para(doc, "Kholis Kamaluddin Wahib", size=14, bold=True, color=DARK_BLUE, space_after=4)
cover_para(doc, "NPM: 4337857201230056", size=12, color=DARK_BLUE, space_after=4)
cover_para(doc, "Study Program: Information System", size=12, color=DARK_BLUE, space_after=12)

cover_para(doc, "Faculty of Information and Computer Technology", size=13, bold=True, color=DARK_BLUE, space_after=4)
cover_para(doc, "HORIZON UNIVERSITY INDONESIA", size=14, bold=True, color=DARK_BLUE, space_after=4)
cover_para(doc, "2026", size=13, bold=True, color=DARK_BLUE, space_after=0)

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# SECTION 2: LETTER OF INTERNSHIP COMPLETION (keep existing scan)
# ════════════════════════════════════════════════════════════════════════════
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("LETTER OF INTERNSHIP COMPLETION")
set_font(r, size_pt=16, bold=True)
para_spacing(p, before=0, after=12, line=1.0)

p2 = add_body(doc, "[INSERT SCAN: Letter of Internship Completion — keep original scanned image from LAPORAN MAGANG - KHOLIS.docx]", indent_first=0)
p2.alignment = WD_ALIGN_PARAGRAPH.CENTER

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# SECTION 3: UNIVERSITY INTERNSHIP MENTOR APPROVAL PAGE
# ════════════════════════════════════════════════════════════════════════════
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = p.add_run("UNIVERSITY INTERNSHIP MENTOR APPROVAL PAGE")
set_font(r, size_pt=16, bold=True)
para_spacing(p, before=0, after=12, line=1.0)

approval_lines = [
    "This Internship Final Report has been reviewed and approved by:",
    "",
    "Student Name   : Kholis Kamaluddin Wahib",
    "NPM            : 4337857201230056",
    "Study Program  : Information System",
    "Faculty        : Faculty of Information and Computer Technology",
    "University     : Horizon University Indonesia",
    "Host Organization : UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR)",
    "                   Dinas Sosial Provinsi Jawa Barat",
    "Internship Period : August 2026 – December 2026 [FILL: exact dates]",
    "",
    "Academic Supervisor:",
    "Muhammad Jembar Jomantara",
    "",
    "On-Site Supervisor:",
    "Bagas Adiantyo Putra, S.Tr.Sos.",
    "",
    "Head of Center:",
    "Shanti Umbara Dewi, S.IP., M.H.",
]

for line in approval_lines:
    p = doc.add_paragraph()
    r = p.add_run(line)
    set_font(r, size_pt=12)
    para_spacing(p, before=0, after=3, line=1.0)

# Signature blocks
doc.add_paragraph()
t = doc.add_table(rows=2, cols=2)
t.alignment = WD_TABLE_ALIGNMENT.CENTER
t.style = "Table Grid"
sig_data = [
    ["Academic Supervisor,", "On-Site Supervisor,"],
    ["\n\n\n\nMuhammad Jembar Jomantara", "\n\n\n\nBagas Adiantyo Putra, S.Tr.Sos."],
]
for i, row_data in enumerate(sig_data):
    for j, text in enumerate(row_data):
        cell = t.rows[i].cells[j]
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        r = p.add_run(text)
        set_font(r, size_pt=12)

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# SECTION 4: TABLE OF CONTENTS
# ════════════════════════════════════════════════════════════════════════════
add_toc(doc)
doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# SECTION 5: PREFACE
# ════════════════════════════════════════════════════════════════════════════

# Start new section with page numbering
new_sec = doc.add_section(WD_SECTION_START.NEW_PAGE)
set_margins(new_sec)
add_page_number_bottom_right(new_sec)

add_heading(doc, "PREFACE", level=1)

preface_text = [
    "Praise and gratitude are offered to God Almighty for His grace and blessings, which have enabled the author to complete this Internship Final Report entitled \"PENDEKAR1721: User Profile Management and Social Services Administration System for UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR), Dinas Sosial Provinsi Jawa Barat\" in fulfillment of the requirements for the Bachelor of Information System program at Horizon University Indonesia.",
    "This report documents the sixteen (16) weeks of internship activities undertaken at UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR), located in Lembang, West Java, from August to December 2026. During this period, the author served as a Backend Developer Intern responsible for the design, development, and deployment of the PENDEKAR1721 web application.",
    "The successful completion of this internship and the preparation of this report would not have been possible without the guidance, support, and cooperation of many parties. The author would like to express sincere gratitude to:",
    "1. Mrs. Shanti Umbara Dewi, S.IP., M.H., as Head of UPTD PPSGBR Lembang, for permitting the author to conduct the internship at the institution;",
    "2. Mr. Bagas Adiantyo Putra, S.Tr.Sos., as On-Site Supervisor, for his invaluable guidance, mentorship, and constructive feedback throughout the internship;",
    "3. Mr. Muhammad Jembar Jomantara, as Academic Supervisor from Horizon University Indonesia, for his academic guidance and support;",
    "4. All staff and colleagues at UPTD PPSGBR Lembang, for their warmth, cooperation, and assistance during the internship period;",
    "5. Family and friends, whose constant encouragement and moral support have been a source of strength throughout this journey.",
    "The author acknowledges that this report is far from perfect. Constructive criticism and suggestions are warmly welcomed for the improvement of future work. It is sincerely hoped that this report will be of benefit to the author, to Horizon University Indonesia, and to UPTD PPSGBR Lembang.",
]

for para in preface_text:
    add_body(doc, para)

sign_p = doc.add_paragraph()
sign_p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
r = sign_p.add_run("Lembang, December 2026\n\n\n\nKholis Kamaluddin Wahib")
set_font(r, size_pt=12)
para_spacing(sign_p, before=12, after=6, line=1.0)

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# CHAPTER I – INTRODUCTION
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "CHAPTER I", level=1)
add_heading(doc, "INTRODUCTION", level=1)

add_heading(doc, "1.1 Background", level=2, numbering="")
add_body(doc, "The rapid advancement of information technology has significantly transformed the way public institutions manage data and deliver services. Social service organizations, in particular, face growing demands to digitize their administrative processes, enhance transparency, and improve the quality of services provided to their beneficiaries. In this context, the development of a reliable, secure, and scalable web-based information system becomes a strategic necessity.")
add_body(doc, "UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) is a technical implementation unit under Dinas Sosial Provinsi Jawa Barat that provides social rehabilitation and vocational training for at-risk youth (remaja binaan). The institution manages various programs including sewing (menjahit), motorcycle repair (montir-motor), car repair (montir-mobil), culinary arts (tata-boga), and social guidance (bimbingan sosial). Prior to the development of PENDEKAR1721, the management of participant profiles, skill records, work experiences, training histories, and attendance data was conducted through fragmented manual processes or separate legacy systems, leading to inefficiencies and data inconsistencies.")
add_body(doc, "To address these challenges, the author undertook a 16-week internship at UPTD PPSGBR as a Backend Developer Intern, tasked with designing and building PENDEKAR1721 — a comprehensive web application for user profile management, master data administration, attendance tracking (SEMARAK module integration), and participant graduation workflow. The system was developed using the Laravel 13 framework with PHP 8.4, Filament 3 admin panels, and a dual-database architecture to integrate the existing SEMARAK attendance application into the new platform.")
add_body(doc, "This internship provided an invaluable opportunity to apply theoretical knowledge gained in the Information System study program to a real-world software development project, while contributing meaningfully to the digital transformation efforts of a public social service institution in West Java.")

add_heading(doc, "1.2 Objectives of the Internship", level=2, numbering="")
add_body(doc, "The internship at UPTD PPSGBR Lembang was undertaken with the following objectives:")
objectives = [
    "1. Technical Competence: To apply and deepen backend development skills, including database design, RESTful API development, authentication and authorization implementation, business logic programming, and deployment support in a real-world professional environment.",
    "2. Analysis and Design Competence: To develop the ability to analyze organizational requirements, translate them into system specifications, and design scalable database schemas and application architectures suitable for social service administration.",
    "3. Professional and Soft Skills: To enhance professional communication, teamwork, time management, documentation writing, and problem-solving skills by working within an institutional setting with real users and stakeholders.",
    "4. Project Contribution: To deliver a functional, secure, and maintainable backend system (PENDEKAR1721) that addresses the real administrative needs of UPTD PPSGBR and can serve as a foundation for future digital services of Dinas Sosial Provinsi Jawa Barat.",
]
for obj in objectives:
    add_body(doc, obj, indent_first=0.3)

add_heading(doc, "1.3 Benefits of the Internship", level=2, numbering="")
add_body(doc, "The internship is expected to yield the following benefits:")

add_heading(doc, "1.3.1 For the Student", level=3, numbering="")
add_body(doc, "The internship provides direct exposure to professional backend software development, bridging the gap between academic theory and industry practice. The student gains hands-on experience with modern PHP/Laravel development, database engineering, security implementation, containerized deployment, and collaborative project management within a real institutional environment.")

add_heading(doc, "1.3.2 For the University", level=3, numbering="")
add_body(doc, "The internship strengthens the university's partnership with public institutions and demonstrates the practical relevance of its Information System curriculum. Outcomes from this internship, including the PENDEKAR1721 system and this report, can serve as case studies and reference materials for future students and academic programs.")

add_heading(doc, "1.3.3 For the Host Organization", level=3, numbering="")
add_body(doc, "UPTD PPSGBR benefits from the development of PENDEKAR1721, a purpose-built web application that streamlines participant data management, integrates legacy attendance records, automates the graduation and alumni activation workflow, and provides dashboards and reporting tools for administrative decision-making. The system directly reduces manual workload and improves data accuracy for the institution's social service operations.")

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# CHAPTER II – COMPANY PROFILE
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "CHAPTER II", level=1)
add_heading(doc, "COMPANY PROFILE", level=1)

add_heading(doc, "2.1 History of the Organization", level=2, numbering="")
add_body(doc, "UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) is a Technical Implementation Unit (Unit Pelaksana Teknis Daerah) under Dinas Sosial Provinsi Jawa Barat (Social Services Office of West Java Province). The institution is located in Lembang, West Bandung Regency (Kabupaten Bandung Barat), West Java, Indonesia.")
add_body(doc, "UPTD PPSGBR is mandated to provide social rehabilitation, mentoring, and vocational training services for at-risk youth (remaja binaan) in West Java. The center accepts young individuals who are referred by families, communities, or government agencies, providing them with structured programs to develop vocational skills, social competence, and professional readiness to re-integrate into society as productive members.")
add_body(doc, "[CONFIRM WITH KHOLIS: Year of establishment and additional historical background of UPTD PPSGBR Lembang if available from official documents.]")

add_heading(doc, "2.2 Vision, Mission, and Objectives", level=2, numbering="")
add_body(doc, "[CONFIRM WITH KHOLIS: Please provide the official Vision, Mission, and Objectives of UPTD PPSGBR Lembang as stated in official organizational documents. The following is a general description based on the institution's mandate.]")
add_body(doc, "As a social service institution under Dinas Sosial Provinsi Jawa Barat, UPTD PPSGBR operates with the overarching mission of empowering at-risk youth through skills-based social rehabilitation, enabling them to achieve self-sufficiency and positive social reintegration. The institution aims to provide accessible, high-quality, and evidence-based social services that uphold the dignity and rights of every participant (anak binaan) under its care.")

add_heading(doc, "2.3 Organizational Structure", level=2, numbering="")
add_body(doc, "UPTD PPSGBR Lembang is led by Mrs. Shanti Umbara Dewi, S.IP., M.H., as Head of Center (Kepala UPTD). The administrative and service delivery functions are supported by various divisions responsible for social work, vocational training coordination, administration, and finance. The author was placed under the supervision of Mr. Bagas Adiantyo Putra, S.Tr.Sos., as the designated On-Site Supervisor for the internship program.")
add_body(doc, "[CONFIRM WITH KHOLIS: Please insert the official organizational chart of UPTD PPSGBR Lembang here, or provide the chart for embedding: INSERT ORGANIZATIONAL CHART IMAGE]")

add_heading(doc, "2.4 Core Activities", level=2, numbering="")
add_body(doc, "UPTD PPSGBR Lembang conducts a range of structured social rehabilitation and vocational training programs for its youth participants (anak binaan). The core programs include:")
activities = [
    "Sewing and Fashion (Menjahit): Vocational training in garment production and fashion design.",
    "Motorcycle Repair (Montir Motor): Technical training in motorcycle maintenance and repair.",
    "Car Repair (Montir Mobil): Technical training in automotive maintenance and repair.",
    "Culinary Arts (Tata Boga): Training in professional cooking, food preparation, and food safety.",
    "Social Guidance (Bimbingan Sosial): Psychosocial counseling, life skills training, and character development activities to support social reintegration.",
]
for act in activities:
    p = doc.add_paragraph()
    r = p.add_run("• " + act)
    set_font(r, size_pt=12)
    para_spacing(p, before=0, after=4, line=1.5)
    p.paragraph_format.left_indent = Inches(0.5)
    p.paragraph_format.first_line_indent = Inches(-0.2)

add_body(doc, "Beyond these programs, UPTD PPSGBR also manages participant intake, assessment, monitoring, and graduation processes, which form the administrative backbone supported by the PENDEKAR1721 system developed during this internship.")

add_heading(doc, "2.5 Products and Services", level=2, numbering="")
add_body(doc, "The primary output of UPTD PPSGBR is not a commercial product but a social service: the rehabilitation and vocational empowerment of at-risk youth. The institution's \"services\" include the structured training programs described above, as well as dormitory accommodation, meals, health support, and psychosocial assistance for participants during their enrollment period.")
add_body(doc, "Upon completion of their training cohort (angkatan), graduates (alumni) are supported in finding employment opportunities through the institution's network of industry partners and alumni placement channels. The PENDEKAR1721 system supports this process by maintaining accurate graduate profiles, employment histories, and skill records accessible to administrators and alumni themselves.")

add_heading(doc, "2.6 Review of Organizational Growth and Trends", level=2, numbering="")
add_body(doc, "[CONFIRM WITH KHOLIS: If official statistics on number of alumni, participants served per year, or growth data are available, please insert them here.]")
add_body(doc, "UPTD PPSGBR Lembang has progressively expanded its capacity to serve at-risk youth from across West Java. The adoption of digital systems, including the PENDEKAR1721 platform, reflects a broader trend within Dinas Sosial Provinsi Jawa Barat to modernize social service administration, improve data quality, and increase the traceability of outcomes for every individual under the institution's care. The integration of SEMARAK (the attendance and evaluation system) into PENDEKAR1721 represents a significant step in consolidating previously fragmented information systems into a single, unified digital platform.")

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# CHAPTER III – INTERNSHIP ACTIVITIES
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "CHAPTER III", level=1)
add_heading(doc, "INTERNSHIP ACTIVITIES", level=1)

add_heading(doc, "3.1 Overview of Internship Activities", level=2, numbering="")
add_body(doc, "The sixteen-week internship at UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR), Lembang, was conducted from [FILL: start date] to [FILL: end date], August to December 2026. Throughout this period, the author served as a Backend Developer Intern assigned to the PENDEKAR1721 project — a full-stack web application developed to modernize and unify the data management and administrative operations of the institution.")
add_body(doc, "The internship was organized into four progressive phases: (1) Orientation and Requirements Analysis, (2) System Foundation Development, (3) Core Feature Implementation, and (4) System Hardening, Deployment, and Documentation. Each phase built upon the previous one, following an iterative development approach aligned with the incremental needs identified during stakeholder discussions.")

add_heading(doc, "3.2 Objectives of Internship Activities", level=2, numbering="")
add_body(doc, "The specific objectives of the internship activities at UPTD PPSGBR were to:")
obj3 = [
    "Design and implement a secure, role-based web application backend for managing participant (anak binaan) profiles, vocational skills, training histories, and work experience records;",
    "Integrate the existing SEMARAK attendance application (a PHP native system) into the new Laravel 13 platform as a modular sub-application, preserving full behavioral parity with the original system;",
    "Develop a graduation and alumni activation workflow enabling administrators to formally graduate youth participants and activate their client accounts in the system;",
    "Implement a data import module supporting Excel and Word document formats for bulk onboarding of new participants and clients;",
    "Build a comprehensive suite of automated feature and security tests to ensure system reliability and correctness;",
    "Deploy the application using Docker and configure it for production hosting within the institution's infrastructure.",
]
for i, o in enumerate(obj3, 1):
    p = doc.add_paragraph()
    r = p.add_run(f"{i}. {o}")
    set_font(r, size_pt=12)
    para_spacing(p, before=0, after=4, line=1.5)
    p.paragraph_format.left_indent = Inches(0.3)

add_heading(doc, "3.3 Responsibilities and Roles", level=2, numbering="")
add_body(doc, "As a Backend Developer Intern, the author's responsibilities encompassed the following areas, all of which are directly evidenced in the PENDEKAR1721 codebase:")

roles = [
    ("Database Design and Migration", "Designed and implemented the relational database schema using Laravel Eloquent migrations. This included the primary application database (users, user_profiles, user_experiences, pivot tables for skills/trainings/work locations, and six master data tables) and the secondary SEMARAK database connection for the Absensi module."),
    ("Authentication and Authorization", "Implemented a unified single-login system (/login) for all user roles (admin, admin_absensi, client), with role-based panel access enforced through Filament's FilamentUser interface and middleware. Implemented PHP 8.4 enum-based user status workflow (Draft/Published/Archived)."),
    ("Business Logic and Service Layer", "Developed core business services including GraduationService (youth graduation and account activation with transaction compensation), ImportService (Excel/Word bulk import with 2-mode support and duplicate detection), ImportBinaanService (cross-database account provisioning), and DasborAdminService (admin dashboard statistics)."),
    ("API Development (Absensi Module)", "Ported the SEMARAK native PHP API to Laravel as a single-endpoint JSON API (/absensi/api?action=...) supporting 11 read/write actions, with rate limiting, global input validation, and behavioral parity verified by 46 fixture oracle tests."),
    ("Security Implementation", "Implemented multi-layer rate limiting on login (5/min per email+IP, 10/min per email, 20/min per IP), anti-enumeration measures, session fixation and hijack protection, security headers middleware (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy), and self-hosted fonts (eliminating external CDN dependencies)."),
    ("Automated Testing", "Wrote feature and unit tests covering authentication, panel authorization, profile privacy, Absensi API parity, graduation workflow (including cross-database compensation), import validation, security headers, and performance benchmarks."),
    ("Deployment and DevOps Support", "Authored the Docker configuration (Dockerfile, compose.yml) for containerized production deployment. Set up Cloudflare Tunnel for temporary public access during stakeholder demonstrations."),
    ("Documentation", "Authored technical documentation for the Absensi module (docs/ABSENSI-MODUL.md), deployment guides, security audit notes, and this internship final report."),
]
for title, desc in roles:
    p = doc.add_paragraph()
    r1 = p.add_run(title + ": ")
    set_font(r1, size_pt=12, bold=True)
    r2 = p.add_run(desc)
    set_font(r2, size_pt=12)
    para_spacing(p, before=0, after=6, line=1.5)
    p.paragraph_format.left_indent = Inches(0.3)

add_heading(doc, "3.4 Work Process and Stages", level=2, numbering="")
add_body(doc, "The development of PENDEKAR1721 followed an iterative, milestone-based approach structured across four phases:")

phases = [
    ("Phase 1 — Orientation and Requirements Analysis (Weeks 1–4)",
     "The first phase involved onboarding at UPTD PPSGBR, understanding the institution's social service business processes, and conducting requirement gathering through discussions with the on-site supervisor and administrative staff. The author studied the existing legacy SEMARAK system and manual data management workflows to define the scope, user roles, and functional requirements for PENDEKAR1721. Technology stack selection (Laravel 13, PHP 8.4, Filament 3, SQLite/MySQL, Docker) was finalized, the development environment was configured, the Git repository was initialized, and the initial database ERD and API design drafts were prepared."),
    ("Phase 2 — System Foundation (Weeks 5–8)",
     "The second phase focused on building the application's core infrastructure. Database migrations and seeders were created for all primary tables. The authentication system (unified login, role guards, Filament panels) was implemented and tested. Core master-data management modules (cities, education degrees, religions, ethnic groups, skills, training types) were developed as full CRUD Filament resources. Input validation and error-handling patterns were established consistently across all endpoints."),
    ("Phase 3 — Core Feature Implementation (Weeks 9–12)",
     "The third phase delivered the main business modules: (a) the full UserResource with comprehensive profile management (personal data, work experience, skills, training, work location, file upload), (b) the Absensi module integrating SEMARAK into Laravel with full API parity and six web pages, (c) the GraduationService implementing the youth graduation workflow, (d) the ImportService supporting bulk data onboarding from Excel and Word documents, (e) admin dashboard widgets with statistical charts, and (f) the public-facing profile directory. Query optimization, feature testing, and bug fixing were also conducted in this phase."),
    ("Phase 4 — Hardening, Deployment, and Documentation (Weeks 13–16)",
     "The final phase concentrated on security hardening (rate limiting, anti-enumeration, session protection, security headers, self-hosted fonts, CSP), performance tuning, User Acceptance Testing (UAT) with UPTD PPSGBR administrators, Docker-based deployment to the institution's server, system handover, final documentation, and the drafting and revision of this internship report."),
]
for title, desc in phases:
    p = doc.add_paragraph()
    r1 = p.add_run(title)
    set_font(r1, size_pt=12, bold=True)
    para_spacing(p, before=6, after=3, line=1.5)
    add_body(doc, desc, indent_first=0.3)

add_heading(doc, "3.5 Project Result: PENDEKAR1721", level=2, numbering="")
add_body(doc, "PENDEKAR1721 is a web-based information system developed for UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) to digitize and unify the management of youth participant data, vocational skills records, attendance evaluations, and graduate alumni profiles. The system name combines \"PENDEKAR\" (an acronym or branding for the application) with \"1721\" as an identifier for this internship cohort.")

add_heading(doc, "3.5.1 System Architecture", level=3, numbering="")
add_body(doc, "PENDEKAR1721 is built on the Laravel 13 MVC framework (PHP 8.4) with the following architectural components:")
arch = [
    "Dual Filament Admin Panels: Admin Panel (/admin, Amber theme) for administrative users; Client Panel (/klien, Green theme) for participant self-service.",
    "Absensi Module (/absensi): A self-contained module integrating the SEMARAK attendance system with its own blade pages, JavaScript, and JSON API, connected to a separate MySQL database.",
    "Front-End Public Pages (/): Landing page, public participant profile directory, and program information pages.",
    "Service Layer: Business logic is encapsulated in dedicated Service classes (ImportService, GraduationService, ImportBinaanService, DasborAdminService, and seven Absensi services).",
    "Database Layer: Primary SQLite/MySQL database for user data; secondary MySQL (semarak) database for attendance records. Cross-database relations handled without SQL JOINs.",
    "Docker Deployment: Single-container PHP 8.4-CLI image with Octane support, volume-mounted SQLite database and storage.",
]
for a in arch:
    p = doc.add_paragraph()
    r = p.add_run("• " + a)
    set_font(r, size_pt=12)
    para_spacing(p, before=0, after=4, line=1.5)
    p.paragraph_format.left_indent = Inches(0.5)
    p.paragraph_format.first_line_indent = Inches(-0.2)

add_heading(doc, "3.5.2 Database Schema (ERD Summary)", level=3, numbering="")
add_body(doc, "[INSERT SCREENSHOT: Entity-Relationship Diagram (ERD) of PENDEKAR1721 database]")
add_body(doc, "The key entities and relationships are summarized in the table below:")

# ERD Table
erd_headers = ["Table", "Primary Key", "Key Relations", "Description"]
erd_data = [
    ["users", "id (bigint)", "→ user_profiles, user_experiences, user_skills, user_trainings, user_work_locations", "Core user accounts with role and status"],
    ["user_profiles", "id (bigint)", "→ users, master_cities (×2), master_education_degrees, master_religions, master_ethnic_groups", "Extended personal data, documents, hire status"],
    ["user_experiences", "id (bigint)", "→ users", "Work experience records per user"],
    ["user_skills", "(pivot)", "users ↔ master_skills", "Many-to-many: user skills"],
    ["user_trainings", "(pivot)", "users ↔ master_trainings", "Many-to-many: training programs attended"],
    ["user_work_locations", "(pivot)", "users ↔ master_cities", "Preferred work location cities"],
    ["master_cities", "id (bigint)", "–", "Reference: cities/regions"],
    ["master_skills", "id (bigint)", "–", "Reference: vocational skills"],
    ["master_trainings", "id (bigint)", "–", "Reference: training program types"],
    ["anak (semarak DB)", "id (string)", "→ users via anak_id", "Youth participant records (attendance DB)"],
    ["evaluasi (semarak DB)", "composite", "→ anak, kegiatan", "Daily attendance/evaluation scores"],
]

tbl_erd = doc.add_table(rows=1 + len(erd_data), cols=4)
tbl_erd.style = "Table Grid"
tbl_erd.alignment = WD_TABLE_ALIGNMENT.CENTER
# Set column widths
col_widths = [Inches(1.4), Inches(1.3), Inches(2.3), Inches(1.6)]
for i, width in enumerate(col_widths):
    for row in tbl_erd.rows:
        row.cells[i].width = width

make_table_header_row(tbl_erd, 0, erd_headers, font_size=10)
for r_idx, row_data in enumerate(erd_data, 1):
    for c_idx, text in enumerate(row_data):
        cell = tbl_erd.rows[r_idx].cells[c_idx]
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        run = p.add_run(text)
        set_font(run, size_pt=10)

doc.add_paragraph()

add_heading(doc, "3.5.3 Main Features and Modules", level=3, numbering="")

# Feature table
feat_headers = ["Module", "Key Features", "Access Role"]
feat_data = [
    ["User Profile Management", "Full CRUD: personal data, photo upload, work experience, skills, training, work location; status workflow (Draft/Published/Archived)", "admin"],
    ["Master Data Management", "CRUD for cities, education degrees, religions, ethnic groups, skills, training types", "admin"],
    ["Import Data", "Bulk import from Excel (.xlsx/.xls/.csv) and Word (.docx); preview & validation; MODE_BINAAN and MODE_KLIEN; duplicate detection; template download", "admin"],
    ["Graduation Workflow", "Graduate youth participant → activate client account; assign one-time 24-char password; cross-DB transaction with compensation on failure", "admin"],
    ["Absensi / SEMARAK", "Dashboard, attendance input (evaluasi), youth data management, attendance summary, report card (rapor), schedule settings; JSON API (11 actions)", "admin, admin_absensi"],
    ["Admin Dashboard", "Statistics widgets: user counts by status/role, cohort distribution chart, account growth chart, alumni composition, action items list", "admin"],
    ["Client Self-Service", "Own profile view/edit, skill and experience management", "client"],
    ["Public Profile Directory", "Slug-based public profile pages for alumni; program information pages (sewing, montir, culinary, social guidance)", "public"],
    ["Authentication", "Unified login with multi-layer rate limiting, anti-enumeration, session protection, security headers", "all"],
]
tbl_feat = doc.add_table(rows=1 + len(feat_data), cols=3)
tbl_feat.style = "Table Grid"
tbl_feat.alignment = WD_TABLE_ALIGNMENT.CENTER
feat_widths = [Inches(1.6), Inches(3.3), Inches(1.2)]
for i, w in enumerate(feat_widths):
    for row in tbl_feat.rows:
        row.cells[i].width = w
make_table_header_row(tbl_feat, 0, feat_headers, font_size=10)
for r_idx, row_data in enumerate(feat_data, 1):
    for c_idx, text in enumerate(row_data):
        cell = tbl_feat.rows[r_idx].cells[c_idx]
        p = cell.paragraphs[0]
        run = p.add_run(text)
        set_font(run, size_pt=10)
doc.add_paragraph()

add_heading(doc, "3.5.4 Screenshots", level=3, numbering="")
screenshots = [
    "Login Page — unified login with PENDEKAR1721 branding and UPTD PPSGBR hero image",
    "Admin Dashboard — statistics overview, charts, and quick-access widgets",
    "User Management (UserResource) — full profile CRUD form in Filament admin panel",
    "Import Data — preview table with validation status per row",
    "Absensi Dashboard — SEMARAK module landing page within PENDEKAR1721",
    "Absensi Input — daily attendance/evaluation input page",
    "Public Profile Directory — alumni profile cards on the landing page",
    "Public Program Pages — vocational program information pages",
]
for ss in screenshots:
    p = doc.add_paragraph()
    r = p.add_run(f"[INSERT SCREENSHOT: {ss}]")
    set_font(r, size_pt=11)
    r.font.italic = True
    para_spacing(p, before=0, after=4, line=1.5)
    p.paragraph_format.left_indent = Inches(0.3)

add_heading(doc, "3.5.5 System Benefits for UPTD PPSGBR / Dinas Sosial", level=3, numbering="")
add_body(doc, "PENDEKAR1721 delivers the following concrete benefits to UPTD PPSGBR and its parent organization, Dinas Sosial Provinsi Jawa Barat:")
benefits = [
    "Centralized data management: All participant profiles, skills, training records, and work experiences are stored and managed in a single, structured database, eliminating data fragmentation.",
    "Automated graduation process: The graduation workflow reduces manual administrative steps and ensures consistent data handling when participants transition from active youth to alumni status.",
    "Integrated attendance system: By integrating SEMARAK into PENDEKAR1721, the institution manages both administrative and evaluation data on a unified platform with a single login system.",
    "Bulk data import: The import module dramatically reduces data entry time when onboarding new cohorts of participants from Excel or Word documents.",
    "Security and reliability: Multiple security layers protect sensitive participant data, while comprehensive automated tests ensure system correctness.",
    "Scalability: The Docker-based deployment and support for MySQL/PostgreSQL enable the system to scale as the institution's data volume grows.",
]
for b in benefits:
    p = doc.add_paragraph()
    r = p.add_run("• " + b)
    set_font(r, size_pt=12)
    para_spacing(p, before=0, after=4, line=1.5)
    p.paragraph_format.left_indent = Inches(0.5)
    p.paragraph_format.first_line_indent = Inches(-0.2)

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# CHAPTER IV – PERSONAL RESULTS, EVALUATION, AND POINTS LEARNED
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "CHAPTER IV", level=1)
add_heading(doc, "PERSONAL RESULTS, EVALUATION, AND POINTS LEARNED", level=1)

add_body(doc, "This chapter addresses the six key evaluation questions stipulated in the internship handbook (Section 6.3.6), organized under three subsections: Skills and Knowledge Gained, Challenges and Solutions, and Personal Evaluation.")

add_heading(doc, "4.1 Skills and Knowledge Gained", level=2, numbering="")

add_heading(doc, "4.1.1 Was the internship beneficial?", level=3, numbering="")
add_body(doc, "Yes, the internship at UPTD PPSGBR Lembang was deeply beneficial. It provided direct, immersive experience in building a production-level web application from requirements gathering through deployment. The opportunity to develop a system that is actively used by real institutional staff and directly impacts the management of vulnerable youth participants gave the work a meaningful context far beyond academic exercises. The internship validated and significantly strengthened the author's technical, analytical, and professional capabilities.")

add_heading(doc, "4.1.2 Theory vs. Real-World Application", level=3, numbering="")
add_body(doc, "The internship significantly deepened the understanding of the gap — and the bridge — between academic theory and professional practice. University coursework provided strong conceptual foundations in database design, software engineering principles, and system security. However, the internship revealed dimensions of real-world development that textbooks rarely capture: the importance of backward compatibility when migrating legacy systems, the complexity of cross-database architectures, the criticality of security hardening in public-facing systems handling sensitive social data, and the need for robust automated testing to maintain confidence in an evolving codebase. For instance, designing the SchemaGuard safety mechanism to allow schema-dependent features to degrade gracefully (rather than crash) when database migrations have not yet been run was a practical engineering judgment not taught in any course, but learned through the real constraints of institutional deployment.")

add_heading(doc, "4.1.3 New Technical Knowledge and Skills Gained", level=3, numbering="")
tech_skills = [
    ("Laravel 13 & PHP 8.4", "Mastery of modern PHP features (PHP 8.4 backed enums, readonly properties, match expressions, arrow functions) and advanced Laravel patterns (service containers, Eloquent scopes, model observers, deferred loading)."),
    ("Filament 3 Admin Framework", "Practical expertise in building complex admin interfaces with custom resources, table actions, form schemas, widgets, and dual-panel configurations."),
    ("Security Implementation", "Hands-on implementation of multi-layer rate limiting, session fixation prevention, CSP headers, anti-enumeration timing guards, and self-hosted font configuration to eliminate CDN dependencies."),
    ("Cross-Database Architecture", "Designing and implementing an application that safely handles two separate database connections (application DB and legacy SEMARAK DB) with compensating transaction logic."),
    ("Automated Testing", "Writing comprehensive feature, unit, and oracle-based tests (46 fixture oracle tests for byte-perfect API parity), and security test suites covering 13 security scenarios."),
    ("Docker & Deployment", "Building a production-ready Docker image with multi-stage PHP extension compilation, health checks, and volume configuration for persistent storage."),
    ("Excel/Word Import Processing", "Building a robust file parser using PhpSpreadsheet and PhpWord that handles multiple file formats, template variants, and content control normalization."),
]
for skill, desc in tech_skills:
    p = doc.add_paragraph()
    r1 = p.add_run(skill + ": ")
    set_font(r1, size_pt=12, bold=True)
    r2 = p.add_run(desc)
    set_font(r2, size_pt=12)
    para_spacing(p, before=0, after=5, line=1.5)
    p.paragraph_format.left_indent = Inches(0.3)

add_heading(doc, "4.1.4 Personal Strengths and Weaknesses", level=3, numbering="")
add_body(doc, "Through this internship, the author identified the following personal strengths: systematic problem decomposition (breaking complex features into well-defined services and test cases), attention to security detail (proactively researching and implementing security controls beyond minimum requirements), and thorough documentation (writing clear technical notes and test fixtures). Areas identified for improvement include: initially underestimating the time required for cross-database integration testing, and the need to improve skills in communicating technical constraints to non-technical stakeholders in accessible terms.")

add_heading(doc, "4.1.5 Contribution to Personal Growth", level=3, numbering="")
add_body(doc, "The internship made a substantial contribution to personal and professional growth. Working within an institutional environment serving vulnerable youth instilled a strong sense of responsibility toward data privacy and system reliability — values that go beyond technical competence. Collaborating with the on-site supervisor and institutional staff refined interpersonal communication skills, professionalism, and the ability to adapt technical approaches based on operational realities. The experience of delivering a working system under real-world constraints significantly increased confidence in professional software development capabilities.")

add_heading(doc, "4.1.6 Influence on Future Career Plans", level=3, numbering="")
add_body(doc, "This internship has reinforced the author's intention to pursue a career in backend web development with a focus on institutional and enterprise information systems. The experience with security engineering, complex business logic, and legacy system integration has opened interest in specialized areas such as application security and system architecture. The meaningful social impact of the PENDEKAR1721 system — directly supporting the management of youth social rehabilitation data — has also inspired consideration of roles in government digital transformation and civil technology (civic tech) organizations.")

add_heading(doc, "4.2 Challenges and Solutions", level=2, numbering="")
add_body(doc, "The table below summarizes the major technical challenges encountered during the internship and the solutions applied:")

ch_headers = ["Challenge", "Impact", "Solution Applied"]
ch_data = [
    ["Cross-database relation between Laravel app DB (users) and SEMARAK DB (anak) without SQL JOIN support", "High — ORM eager loading failed; N+1 risk", "Implemented separate queries + in-memory mapping; introduced SchemaGuard to disable features gracefully if migration not yet run"],
    ["Preserving 100% behavioral parity with PHP native SEMARAK API responses (array vs. envelope, status codes)", "High — breaking parity would break existing front-end", "Captured 46 fixture oracle files from native system; wrote byte-comparison tests; matched all edge cases identified"],
    ["Bulk import of Excel/Word with two different template formats (horizontal table vs. block form)", "Medium — parser needed to detect format dynamically", "Built adaptive header-scanning parser with multi-format support; added content control flattening for Word dropdowns"],
    ["Duplicate detection for youth (anak) with identical names across cohorts", "Medium — naive name match caused false positives", "Implemented multi-field identity: name + KTP address + birth date + origin + cohort"],
    ["Session-based rate limiting across proxy servers (IP spoofing risk)", "High — rate limit could be bypassed", "Required TRUSTED_PROXIES config; documented clearly; added X-Forwarded-For warning in .env.example"],
    ["Docker image requiring PHP ≥8.4.1 (Symfony 8.1 incompatibility with older versions)", "Medium — silent failure on wrong PHP version", "Added explicit PHP version check at build time (Dockerfile RUN assertion); documented in REBUILD-PHP84.md"],
    ["Graduation transaction atomicity across two database connections", "High — partial failure could corrupt data", "Implemented explicit compensation logic: rollback account creation, restore prior status on failure; logged all rollbacks"],
]
tbl_ch = doc.add_table(rows=1 + len(ch_data), cols=3)
tbl_ch.style = "Table Grid"
tbl_ch.alignment = WD_TABLE_ALIGNMENT.CENTER
ch_widths = [Inches(2.1), Inches(1.4), Inches(2.6)]
for i, w in enumerate(ch_widths):
    for row in tbl_ch.rows:
        row.cells[i].width = w
make_table_header_row(tbl_ch, 0, ch_headers, font_size=10)
for r_idx, row_data in enumerate(ch_data, 1):
    for c_idx, text in enumerate(row_data):
        cell = tbl_ch.rows[r_idx].cells[c_idx]
        p = cell.paragraphs[0]
        run = p.add_run(text)
        set_font(run, size_pt=10)
doc.add_paragraph()

add_heading(doc, "4.3 Personal Evaluation", level=2, numbering="")
add_body(doc, "Overall, the sixteen-week internship at UPTD PPSGBR is evaluated as a highly successful learning experience. All primary deliverables were completed: the PENDEKAR1721 system was developed, tested, deployed, and handed over to the institution with technical documentation. The Absensi (SEMARAK) module integration was achieved with verified behavioral parity. The graduation workflow, import module, and security hardening were completed within the internship period.")
add_body(doc, "The internship fully met the three stated objectives: deepening technical competence in backend development, improving system analysis and design skills, and developing professional soft skills. The author rates the institutional environment at UPTD PPSGBR as highly conducive to learning, with a supportive supervisor who provided clear guidance and constructive feedback throughout the engagement.")
add_body(doc, "If repeated, the author would allocate more time in Phase 1 for deeper stakeholder interviews to surface edge cases earlier, reducing the rework required during Phases 3 and 4. Nevertheless, the challenges encountered — particularly in cross-database integration and legacy system migration — provided the most valuable learning opportunities of the entire internship.")

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# CHAPTER V – RECOMMENDATIONS
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "CHAPTER V", level=1)
add_heading(doc, "RECOMMENDATIONS", level=1)

add_heading(doc, "5.1 Recommendations for UPTD PPSGBR / Dinas Sosial Provinsi Jawa Barat", level=2, numbering="")
add_body(doc, "Based on the internship experience and observations of the institutional context, the following recommendations are respectfully offered to UPTD PPSGBR and its parent organization, Dinas Sosial Provinsi Jawa Barat, regarding the ongoing use and maintenance of PENDEKAR1721:")

recs_org = [
    ("1. Regular Database Backups", "Although PENDEKAR1721 currently uses a SQLite database for development and initial production deployment, it is strongly recommended that daily automated backups be implemented and verified regularly. For production scale, migrating to MySQL or PostgreSQL with a managed backup solution (e.g., automated mysqldump scripts, cloud-based snapshots) will ensure data durability."),
    ("2. Security Updates and Dependency Maintenance", "Laravel, PHP, and all third-party libraries used in PENDEKAR1721 must be updated regularly to receive security patches. It is recommended that a designated technical staff member or IT officer perform quarterly dependency audits using 'composer audit' and apply updates promptly. The BCRYPT_ROUNDS and session security settings in .env should be reviewed annually."),
    ("3. Credential and Secret Management", "The default seeder accounts (admin@pendekar1721.test, etc.) must be removed or their passwords changed before or immediately after production deployment. The APP_KEY and all sensitive .env values must be treated as secrets and never committed to version control."),
    ("4. User Training and Onboarding", "Administrative staff should receive structured training sessions on the PENDEKAR1721 system, particularly for the Import Data module (Excel/Word templates), the Graduation workflow, and the Absensi (SEMARAK) module. Training materials and video tutorials are recommended for sustainable, long-term system adoption."),
    ("5. System Documentation Maintenance", "Technical documentation (README.md, docs/ folder) should be updated whenever the system is modified. If new developers are onboarded to maintain or extend the system, a formal code handover session and documentation review are essential."),
    ("6. Gradual Rollout of Phase 2/3 Features", "The PENDEKAR1721 codebase includes Phase 2 and Phase 3 features (users.anak_id cross-database linking, graduated_at alumni tracking) that require database migrations to be run. These migrations should be applied in a planned maintenance window with a database backup taken immediately beforehand."),
    ("7. Performance Scaling", "Should the number of users, participants, or data volumes grow significantly, it is recommended to migrate from SQLite to MySQL/PostgreSQL, enable Laravel Octane (with Swoole or RoadRunner), and configure a proper Redis cache for session and rate-limiting data, as indicated in the .env configuration."),
    ("8. Future Feature Development", "It is recommended that UPTD PPSGBR formalize a product roadmap for PENDEKAR1721, including potential features such as SMS/email notifications for alumni, integration with provincial Dinas Sosial reporting systems, and mobile-responsive participant self-registration. Engaging Horizon University Indonesia students for future internships can sustain ongoing development."),
]
for title, desc in recs_org:
    p = doc.add_paragraph()
    r1 = p.add_run(title + ": ")
    set_font(r1, size_pt=12, bold=True)
    r2 = p.add_run(desc)
    set_font(r2, size_pt=12)
    para_spacing(p, before=0, after=6, line=1.5)
    p.paragraph_format.left_indent = Inches(0.3)

add_heading(doc, "5.2 Recommendations for Horizon University Indonesia", level=2, numbering="")
add_body(doc, "Based on the internship experience, the following recommendations are offered to Horizon University Indonesia to enhance the quality and relevance of its internship program for Information System students:")

recs_uni = [
    ("1. Strengthen Backend Development Curriculum", "The internship revealed high demand for advanced backend skills (service layers, database migrations, security hardening, containerization, API design) that could be more systematically covered in the university curriculum. It is recommended to expand elective courses in backend web frameworks (Laravel, Django, Spring Boot) and DevOps fundamentals."),
    ("2. Introduce Automated Testing Practices", "Automated testing (PHPUnit, feature tests, security tests) was central to ensuring PENDEKAR1721's correctness. University courses should introduce test-driven development (TDD) and automated testing as a standard practice, not an optional topic."),
    ("3. Include a Docker and Deployment Module", "Many internship students encounter deployment challenges because containerization and production environment configuration are rarely taught. A practical module on Docker, CI/CD basics, and server deployment would significantly improve internship readiness."),
    ("4. Provide Clearer Internship Report Guidelines", "The handbook's guidance on the Monthly Report format and Chapter III content is appreciated; however, additional examples of completed reports from previous students (anonymized) would help future interns understand the expected depth and quality."),
    ("5. Facilitate Industry Partnerships with Public Institutions", "The internship at UPTD PPSGBR demonstrated that government and public social service institutions can offer rich, meaningful, and technically demanding internship projects. The university is encouraged to formalize partnerships with more public sector organizations to offer diverse internship placements aligned with the UN Sustainable Development Goals (SDGs)."),
]
for title, desc in recs_uni:
    p = doc.add_paragraph()
    r1 = p.add_run(title + ": ")
    set_font(r1, size_pt=12, bold=True)
    r2 = p.add_run(desc)
    set_font(r2, size_pt=12)
    para_spacing(p, before=0, after=6, line=1.5)
    p.paragraph_format.left_indent = Inches(0.3)

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# REFERENCES
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "REFERENCES", level=1)

refs = [
    "Laravel LLC. (2024). *Laravel 13.x documentation*. Retrieved from https://laravel.com/docs/13.x",
    "Filament. (2024). *Filament v3 documentation*. Retrieved from https://filamentphp.com/docs",
    "Livewire. (2024). *Livewire v3 documentation*. Retrieved from https://livewire.laravel.com",
    "The PHP Group. (2024). *PHP 8.4 manual*. Retrieved from https://www.php.net/manual/en/",
    "PHPOffice. (2024). *PhpSpreadsheet documentation*. Retrieved from https://phpspreadsheet.readthedocs.io",
    "PHPOffice. (2024). *PhpWord documentation*. Retrieved from https://phpword.readthedocs.io",
    "Docker Inc. (2024). *Docker documentation*. Retrieved from https://docs.docker.com",
    "Vite. (2024). *Vite documentation*. Retrieved from https://vitejs.dev",
    "Tailwind Labs. (2024). *Tailwind CSS v3 documentation*. Retrieved from https://tailwindcss.com/docs",
    "OWASP Foundation. (2021). *OWASP Top Ten 2021*. Retrieved from https://owasp.org/www-project-top-ten/",
    "Horizon University Indonesia. (2024). *Internship handbook academic year 2024/2025*. Faculty of Information and Computer Technology.",
]
for ref in refs:
    p = doc.add_paragraph()
    r = p.add_run(ref)
    set_font(r, size_pt=12)
    pf = p.paragraph_format
    pf.space_before = Pt(0)
    pf.space_after  = Pt(6)
    pf.left_indent      = Inches(0.5)
    pf.first_line_indent = Inches(-0.5)
    pf.line_spacing_rule = WD_LINE_SPACING.SINGLE

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# APPENDIX SECTION — INTERNSHIP MONTHLY REPORT
# ════════════════════════════════════════════════════════════════════════════
add_heading(doc, "APPENDIX", level=1)
add_heading(doc, "INTERNSHIP MONTHLY REPORT", level=1)

add_body(doc, "The following Monthly Reports cover the full 16-week internship period (4 months × 4 weeks). Each month includes weekly summaries and a monthly narrative summary, structured per Handbook Appendix 6.")
add_body(doc, "Note: Exact dates marked [FILL: DD/MM/YYYY] should be filled in by the student before submission.")

doc.add_page_break()

# ─── MONTHLY REPORT HELPER FUNCTIONS ───────────────────────────────────────

def add_monthly_header(doc, month_num, name, job_title, company, department,
                        supervisor, sup_title, work_hours, period):
    p = doc.add_paragraph()
    r = p.add_run(f"INTERNSHIP MONTHLY REPORT — MONTH {month_num}")
    set_font(r, size_pt=14, bold=True)
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    para_spacing(p, before=6, after=6, line=1.0)

    headers = ["Name", "Job Title", "Company's Name", "Department",
               "Supervisor's Name", "Supervisor Title", "Work Hours", "Period of Report"]
    values  = [name, job_title, company, department,
               supervisor, sup_title, work_hours, period]

    t = doc.add_table(rows=len(headers), cols=2)
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.LEFT
    for i, (h, v) in enumerate(zip(headers, values)):
        c0 = t.rows[i].cells[0]
        c1 = t.rows[i].cells[1]
        c0.width = Inches(1.9)
        c1.width = Inches(4.7)
        p0 = c0.paragraphs[0]
        r0 = p0.add_run(h)
        set_font(r0, size_pt=11, bold=True)
        tc0 = c0._tc
        tcPr = tc0.get_or_add_tcPr()
        shd = OxmlElement("w:shd")
        shd.set(qn("w:val"), "clear"); shd.set(qn("w:color"), "auto"); shd.set(qn("w:fill"), "D9E1F2")
        tcPr.append(shd)
        p1 = c1.paragraphs[0]
        r1 = p1.add_run(v)
        set_font(r1, size_pt=11)
    doc.add_paragraph()


def add_weekly_table(doc, week_num, rows_data):
    """
    rows_data: list of 5 dicts: {day, activities, learning, notes}
    """
    p = doc.add_paragraph()
    r = p.add_run(f"Weekly Summary — Week {week_num}")
    set_font(r, size_pt=12, bold=True)
    para_spacing(p, before=6, after=4, line=1.0)

    col_headers = ["Day", "Main Activities", "Learning and Skills Acquired", "Additional Notes"]
    t = doc.add_table(rows=1 + len(rows_data), cols=4)
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.LEFT

    # Set column widths
    widths = [Inches(0.6), Inches(2.4), Inches(2.2), Inches(1.4)]
    for row in t.rows:
        for i, w in enumerate(widths):
            row.cells[i].width = w

    make_table_header_row(t, 0, col_headers, font_size=10)

    for r_idx, d in enumerate(rows_data, 1):
        cells_data = [d["day"], d["activities"], d["learning"], d["notes"]]
        for c_idx, text in enumerate(cells_data):
            cell = t.rows[r_idx].cells[c_idx]
            cell.vertical_alignment = WD_ALIGN_VERTICAL.TOP
            p = cell.paragraphs[0]
            run = p.add_run(text)
            set_font(run, size_pt=10)
    doc.add_paragraph()


def add_monthly_summary(doc, month_num, q1, q2, q3):
    p = doc.add_paragraph()
    r = p.add_run(f"Summary Report — Month {month_num}")
    set_font(r, size_pt=12, bold=True)
    para_spacing(p, before=6, after=4, line=1.0)

    questions = [
        ("1. Principal assignments and responsibilities this month:", q1),
        ("2. Points learned this month:", q2),
        ("3. Problems / challenges encountered and how they were solved:", q3),
    ]
    t = doc.add_table(rows=len(questions), cols=2)
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.LEFT
    for i, (q, a) in enumerate(questions):
        c0 = t.rows[i].cells[0]
        c1 = t.rows[i].cells[1]
        c0.width = Inches(2.4)
        c1.width = Inches(4.2)
        tc0 = c0._tc
        tcPr = tc0.get_or_add_tcPr()
        shd = OxmlElement("w:shd")
        shd.set(qn("w:val"),"clear"); shd.set(qn("w:color"),"auto"); shd.set(qn("w:fill"),"D9E1F2")
        tcPr.append(shd)
        r0 = c0.paragraphs[0].add_run(q)
        set_font(r0, size_pt=10, bold=True)
        r1 = c1.paragraphs[0].add_run(a)
        set_font(r1, size_pt=10)
    doc.add_paragraph()


def add_signature_block(doc):
    t = doc.add_table(rows=4, cols=2)
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    sig_rows = [
        ["Submitted by:", "Read and endorsed by:"],
        ["Name of the Intern:", "On-Site Supervisor:"],
        ["\n\n\n", "\n\n\n"],
        ["Kholis Kamaluddin Wahib\nDate: _______________", "Bagas Adiantyo Putra, S.Tr.Sos.\nDate: _______________"],
    ]
    for i, row_data in enumerate(sig_rows):
        for j, text in enumerate(row_data):
            cell = t.rows[i].cells[j]
            cell.width = Inches(3.2)
            p = cell.paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            r = p.add_run(text)
            set_font(r, size_pt=11, bold=(i in [0, 1]))
    doc.add_paragraph()

# ─── MONTH 1: ORIENTATION & ANALYSIS (Weeks 1–4) ────────────────────────────

add_monthly_header(doc,
    month_num=1,
    name="Kholis Kamaluddin Wahib",
    job_title="Intern (Backend Developer)",
    company="UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) — Dinas Sosial Prov. Jawa Barat",
    department="Information Technology / Administration",
    supervisor="Bagas Adiantyo Putra, S.Tr.Sos.",
    sup_title="On-Site Supervisor",
    work_hours="[FILL: e.g., 08:00–16:00 WIB (40 hrs/week)]",
    period="Month 1 — Weeks 1–4: [FILL: DD/MM/2026] – [FILL: DD/MM/2026]"
)

# Week 1
add_weekly_table(doc, 1, [
    {"day":"Day 1","activities":"Orientation at UPTD PPSGBR Lembang: introduction to institution, staff, and facilities.\n– Tools: official documents, organizational chart","learning":"Understanding UPTD PPSGBR's mandate, structure, and social rehabilitation programs for youth (remaja binaan).","notes":"Welcome meeting with Head of Center and supervisor."},
    {"day":"Day 2","activities":"Study of existing SEMARAK (attendance) system and manual administrative workflows.\n– Tools: SEMARAK native PHP app, browser dev tools","learning":"Identifying pain points in fragmented data management; understanding SEMARAK's data model (anak, kegiatan, evaluasi tables).","notes":"Discussion with supervisor."},
    {"day":"Day 3","activities":"Requirement gathering sessions with administrative staff: participant data, skill tracking, training records.\n– Tools: interview notes, Google Docs","learning":"Translating operational needs into functional requirements; practicing active listening with non-technical stakeholders.","notes":"-"},
    {"day":"Day 4","activities":"Analysis of existing data formats (Excel participant lists, Word intake forms) and data quality assessment.\n– Tools: Microsoft Excel, LibreOffice","learning":"Understanding real-world data inconsistencies and import challenges.","notes":"-"},
    {"day":"Day 5","activities":"Initial scope definition and user role mapping for PENDEKAR1721 (admin, admin_absensi, client roles).\n– Tools: Google Docs, draw.io (diagram draft)","learning":"Learning how organizational hierarchy maps to system access control design.","notes":"Weekly review with supervisor."},
])

# Week 2
add_weekly_table(doc, 2, [
    {"day":"Day 1","activities":"Technology stack selection and justification: Laravel 13, PHP 8.4, Filament 3, SQLite/MySQL, Vite.\n– Tools: Laravel docs, PHP docs, Filament docs","learning":"Evaluating framework trade-offs for a social services context; justifying tech choices to supervisor.","notes":"-"},
    {"day":"Day 2","activities":"Development environment setup: PHP 8.4.1 install, Composer 2, Node.js 18, Laragon configuration.\n– Tools: Laragon, Composer, Node.js, VSCode","learning":"Hands-on environment troubleshooting (PHP version conflicts, extension requirements).","notes":"-"},
    {"day":"Day 3","activities":"Git repository initialization, .gitignore configuration, initial Laravel 13 project scaffold.\n– Tools: Git, GitHub/GitLab, Laravel CLI (artisan)","learning":"Establishing version control workflow from day one of a new project.","notes":"-"},
    {"day":"Day 4","activities":"Initial database ERD draft: entities (users, user_profiles, user_experiences, master tables), relationships, and normalization.\n– Tools: draw.io, dbdiagram.io","learning":"Applying database normalization principles to real institutional data requirements.","notes":"ERD reviewed with supervisor."},
    {"day":"Day 5","activities":"ERD revision based on supervisor feedback; definition of nullable fields for social service context (incomplete data at intake).\n– Tools: draw.io, Markdown notes","learning":"Adapting database design to reflect real-world data availability constraints.","notes":"Sprint planning for Week 3."},
])

# Week 3
add_weekly_table(doc, 3, [
    {"day":"Day 1","activities":"Detailed database schema design: users table with user_type, status enum, password nullable for new binaan accounts.\n– Tools: Laravel migration docs, dbdiagram.io","learning":"Designing for future phases: nullable credentials for pre-active accounts.","notes":"-"},
    {"day":"Day 2","activities":"SEMARAK native system deep analysis: mapping all PHP API actions (dashboard, anak, evaluasi, etc.) to understand parity requirements.\n– Tools: PHP native SEMARAK source code, browser, Postman-equivalent manual testing","learning":"Understanding legacy PHP architecture and the challenge of behavioral migration to Laravel.","notes":"-"},
    {"day":"Day 3","activities":"API design draft for PENDEKAR1721: define action-based JSON API for Absensi module (?action=... pattern for parity).\n– Tools: Markdown API spec, draw.io flow diagrams","learning":"Designing APIs that maintain backward compatibility with an existing front-end.","notes":"-"},
    {"day":"Day 4","activities":"Research on Filament 3 dual-panel configuration: separate Admin and Client panels with independent auth and routing.\n– Tools: Filament 3 documentation, GitHub examples","learning":"Understanding Filament panel providers and FilamentUser interface for role-based access.","notes":"-"},
    {"day":"Day 5","activities":"Draft internship activity plan (Month 2–4 milestones) and present to supervisor for alignment.\n– Tools: Google Docs, project planning template","learning":"Milestone-based project planning and communicating technical roadmaps to non-technical supervisors.","notes":"Monthly plan approved by supervisor."},
])

# Week 4
add_weekly_table(doc, 4, [
    {"day":"Day 1","activities":"Finalize ERD and present database design for supervisor sign-off. Identify secondary SEMARAK database connection requirements.\n– Tools: draw.io, ERD export, supervisor meeting","learning":"Cross-database architecture planning (primary app DB + SEMARAK MySQL DB).","notes":"ERD finalized."},
    {"day":"Day 2","activities":"Study SEMARAK database tables (anak, kegiatan, evaluasi, jadwal_kegiatan, masa_pembinaan, catatan_rapor) from existing DB.\n– Tools: MySQL Workbench, phpMyAdmin, SEMARAK source","learning":"Mapping legacy database to new Eloquent models; understanding non-incrementing string primary keys.","notes":"-"},
    {"day":"Day 3","activities":"Research PHP 8.4 enum types for UserStatus (Draft/Published/Archived) and their integration with Filament HasLabel interface.\n– Tools: PHP 8.4 docs, Filament enum docs","learning":"Using PHP 8.4 backed enums for type-safe status management with built-in display labels.","notes":"-"},
    {"day":"Day 4","activities":"Document Month 1 findings: requirements document, ERD, API spec draft, and technology justification memo.\n– Tools: Google Docs, Markdown","learning":"Technical documentation skills: translating analysis findings into structured reference documents.","notes":"-"},
    {"day":"Day 5","activities":"Month 1 review with supervisor: present all deliverables, receive feedback, adjust plan for Month 2.\n– Tools: presentation, Google Docs","learning":"Formal milestone review communication and incorporating stakeholder feedback into development plan.","notes":"Month 1 sign-off meeting."},
])

add_monthly_summary(doc, 1,
    q1="Principal activities: institutional orientation; requirements gathering with staff; SEMARAK legacy system analysis; database ERD design; API design draft; technology stack selection; development environment setup; Git repository initialization; Month 2 planning.",
    q2="Learned UPTD PPSGBR's social service processes and data management challenges. Deepened understanding of legacy-to-modern migration planning, cross-database architecture design, and PHP 8.4 enum usage. Practiced stakeholder communication and requirement elicitation.",
    q3="Challenge: Aligning the SEMARAK API behavioral requirements (array vs. envelope responses, specific status codes) with Laravel's conventions. Solution: Documented all behavioral requirements in a spec before implementation; planned oracle-based testing to verify parity."
)
add_signature_block(doc)

doc.add_page_break()

# ─── MONTH 2: FOUNDATION (Weeks 5–8) ────────────────────────────────────────

add_monthly_header(doc,
    month_num=2,
    name="Kholis Kamaluddin Wahib",
    job_title="Intern (Backend Developer)",
    company="UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) — Dinas Sosial Prov. Jawa Barat",
    department="Information Technology / Administration",
    supervisor="Bagas Adiantyo Putra, S.Tr.Sos.",
    sup_title="On-Site Supervisor",
    work_hours="[FILL: e.g., 08:00–16:00 WIB (40 hrs/week)]",
    period="Month 2 — Weeks 5–8: [FILL: DD/MM/2026] – [FILL: DD/MM/2026]"
)

# Week 5
add_weekly_table(doc, 5, [
    {"day":"Day 1","activities":"Create initial Laravel migrations: users table (user_type, status, nullable password), create_cache_table, create_jobs_table.\n– Tools: Laravel artisan (make:migration), VSCode","learning":"Structuring database migrations for incremental schema evolution; nullable fields for phased activation.","notes":"-"},
    {"day":"Day 2","activities":"Create commons migration: all master tables (master_cities, master_skills, master_trainings, master_religions, master_ethnic_groups, master_education_degrees) in one migration.\n– Tools: Laravel artisan, Schema::create()","learning":"Grouping related reference-data tables in a single migration for atomic deployment.","notes":"-"},
    {"day":"Day 3","activities":"Create user_profiles, user_experiences, user_skills (pivot), user_trainings (pivot), user_work_locations (pivot) migrations.\n– Tools: Laravel artisan, Eloquent relationships docs","learning":"Implementing many-to-many pivot tables and one-to-one/one-to-many relations in Eloquent.","notes":"-"},
    {"day":"Day 4","activities":"Write database seeders: admin user, master religions, ethnic groups, cities, education degrees, skills, trainings.\n– Tools: Laravel artisan (make:seeder), Faker","learning":"Using seeders for reproducible development environments and demo data.","notes":"-"},
    {"day":"Day 5","activities":"Run migration and seeding, verify all tables created correctly, test artisan migrate:fresh --seed.\n– Tools: Laravel artisan, SQLite browser, phpMyAdmin","learning":"Database migration workflow; rollback and re-run patterns.","notes":"Weekly review with supervisor."},
])

# Week 6
add_weekly_table(doc, 6, [
    {"day":"Day 1","activities":"Implement User Eloquent model: fillable, hidden, casts (UserStatus enum, password hashed), relationships (hasOne profile, hasMany experiences, belongsToMany skills/trainings/cities).\n– Tools: VSCode, PHP 8.4 docs, Eloquent docs","learning":"Eloquent model design with PHP 8.4 backed enums, cast system, and relationship definitions.","notes":"-"},
    {"day":"Day 2","activities":"Implement UserProfile, UserExperience, MasterSkill, MasterTraining, MasterCity, MasterEducationDegree, MasterReligion, MasterEthnicGroup Eloquent models.\n– Tools: VSCode, Eloquent docs","learning":"Consistent model design patterns across the full entity set.","notes":"-"},
    {"day":"Day 3","activities":"Implement PHP 8.4 UserStatus enum (Draft/Published/Archived) with Filament HasLabel interface for display labels.\n– Tools: PHP 8.4 backed enum docs, Filament enum docs","learning":"Enum-driven status management; interface implementation for framework integration.","notes":"-"},
    {"day":"Day 4","activities":"Configure AdminPanelProvider (Filament /admin panel, Amber color theme, auth guard, middleware) and ClientPanelProvider (/klien, Green theme).\n– Tools: Filament 3 panel provider docs, AppServiceProvider","learning":"Filament dual-panel architecture; role-based panel routing and authentication.","notes":"-"},
    {"day":"Day 5","activities":"Implement User::canAccessPanel() logic: check UserStatus first (Draft/Archived blocked), then match panel ID to user_type.\n– Tools: Filament FilamentUser interface, VSCode","learning":"Gate-based access control pattern; defense-in-depth account status checking.","notes":"Sprint review."},
])

# Week 7
add_weekly_table(doc, 7, [
    {"day":"Day 1","activities":"Build MasterCityResource, MasterEducationDegreeResource, MasterEthnicGroupResource in Filament: list, create, edit, delete with search and sort.\n– Tools: Filament make:filament-resource artisan, VSCode","learning":"Filament resource scaffolding; table column and form component configuration.","notes":"-"},
    {"day":"Day 2","activities":"Build MasterReligionResource, MasterSkillResource (with detail field), MasterTrainingResource (with detail field) in Filament.\n– Tools: Filament resources, VSCode","learning":"Adding optional detail/description fields to reference data resources.","notes":"-"},
    {"day":"Day 3","activities":"Implement unified LoginController: show (GET /login), store (POST /login with throttle:login middleware), destroy (POST /logout).\n– Tools: Laravel Auth docs, AppServiceProvider, VSCode","learning":"Custom auth controller implementation; session regeneration, redirect by role (dashboardUrl()).","notes":"-"},
    {"day":"Day 4","activities":"Configure multi-layer rate limiting in AppServiceProvider: throttle:login (5/min per email+IP, 10/min per email, 20/min per IP).\n– Tools: Laravel RateLimiter, AppServiceProvider, .env","learning":"Composite rate limiting with multiple dimensions (IP, email, combined) for brute-force protection.","notes":"-"},
    {"day":"Day 5","activities":"Implement anti-enumeration: constant-time response for unknown emails using Hash::check with dummy hash.\n– Tools: Laravel Hash facade, LoginController","learning":"Timing-attack defense; why constant-time comparison matters for user enumeration prevention.","notes":"Weekly check-in with supervisor."},
])

# Week 8
add_weekly_table(doc, 8, [
    {"day":"Day 1","activities":"Begin UserResource in Filament (admin panel): define table columns (name, email, user_type, status badge, has_current_job indicator).\n– Tools: Filament Tables, VSCode","learning":"Filament table with badge columns, computed state, and sortable/searchable fields.","notes":"-"},
    {"day":"Day 2","activities":"Implement UserResource form: personal info section, status and role selects, profile picture upload, password fields.\n– Tools: Filament Forms, FileUpload component, VSCode","learning":"Complex nested form schemas; conditional field visibility; file upload configuration.","notes":"-"},
    {"day":"Day 3","activities":"Add relational sections to UserResource form: skills (CheckboxList), trainings (CheckboxList), work locations (CheckboxList) using belongsToMany.\n– Tools: Filament CheckboxList, Eloquent BelongsToMany","learning":"Managing many-to-many relationships via Filament form components.","notes":"-"},
    {"day":"Day 4","activities":"Write first feature tests: ExampleTest (HTTP 200 for home/login), Auth login tests (valid/invalid credentials, rate limit response).\n– Tools: PHPUnit, Laravel testing docs, php artisan test","learning":"Writing feature tests with database transactions; testing rate limiting behavior.","notes":"-"},
    {"day":"Day 5","activities":"Month 2 review: present completed database schema, all master data resources, auth system, and initial UserResource to supervisor.\n– Tools: browser demo, VSCode","learning":"Communicating development progress with live demonstrations; responding to usability feedback.","notes":"Month 2 review meeting."},
])

add_monthly_summary(doc, 2,
    q1="Principal activities: implemented all database migrations and seeders; built Eloquent models for the full entity set; configured dual Filament panels (Admin + Client); built all master data CRUD resources; implemented unified login with multi-layer rate limiting and anti-enumeration; began UserResource development; wrote initial feature tests.",
    q2="Gained deep understanding of Filament 3 panel architecture and resource building. Learned PHP 8.4 enum integration with Filament. Practiced multi-layer rate limiting design and implemented session-fixation prevention. Gained experience writing feature tests for authenticated Laravel applications.",
    q3="Challenge: Configuring two independent Filament panels with separate auth guards but a single login page. Solution: Customized canAccessPanel() to check user status first (blocking Draft/Archived), then route by user_type; suppressed Filament's default panel login pages by configuring the unified /login route."
)
add_signature_block(doc)

doc.add_page_break()

# ─── MONTH 3: CORE FEATURES (Weeks 9–12) ─────────────────────────────────────

add_monthly_header(doc,
    month_num=3,
    name="Kholis Kamaluddin Wahib",
    job_title="Intern (Backend Developer)",
    company="UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) — Dinas Sosial Prov. Jawa Barat",
    department="Information Technology / Administration",
    supervisor="Bagas Adiantyo Putra, S.Tr.Sos.",
    sup_title="On-Site Supervisor",
    work_hours="[FILL: e.g., 08:00–16:00 WIB (40 hrs/week)]",
    period="Month 3 — Weeks 9–12: [FILL: DD/MM/2026] – [FILL: DD/MM/2026]"
)

# Week 9
add_weekly_table(doc, 9, [
    {"day":"Day 1","activities":"Complete UserResource: add work experience repeater section (job name, year, description, is_current_job flag).\n– Tools: Filament Repeater, VSCode","learning":"Filament Repeater for one-to-many nested data; managing hasMany relations in Filament forms.","notes":"-"},
    {"day":"Day 2","activities":"Build Absensi module structure: create app/Modules/Absensi/ namespace, 6 Eloquent models (Anak, Kegiatan, Evaluasi, JadwalKegiatan, MasaPembinaan, CatatanRapor).\n– Tools: VSCode, PHP artisan make:model","learning":"Cross-database Eloquent models with explicit $connection = 'semarak'; non-incrementing string PKs.","notes":"-"},
    {"day":"Day 3","activities":"Implement AnakService, EvaluasiService, JadwalService, RingkasanService, RaporService, DasborService, PembinaanService in Absensi module.\n– Tools: VSCode, Absensi native PHP source","learning":"Porting native PHP business logic to Laravel service classes; maintaining behavioral parity.","notes":"-"},
    {"day":"Day 4","activities":"Implement Absensi ApiController (?action= pattern): handleRead() and handleWrite() dispatch with 11 actions, global date range validation.\n– Tools: Laravel Request, VSCode","learning":"Single-endpoint API pattern; structured exception handling with ApiException custom class.","notes":"-"},
    {"day":"Day 5","activities":"Implement Absensi PageController: 6 Blade pages (dashboard, input, anak, summary, rapor, pengaturan) with EnsureAbsensiAdmin middleware.\n– Tools: Laravel Blade, Middleware, VSCode","learning":"Modular Blade page architecture within Laravel application; role middleware patterns.","notes":"Weekly check-in."},
])

# Week 10
add_weekly_table(doc, 10, [
    {"day":"Day 1","activities":"Build Absensi Blade views and SCSS styles matching SEMARAK original design (purple-blue gradient, semarak-card components).\n– Tools: Blade, SCSS, Tailwind, Vite, npm run build","learning":"Maintaining visual design parity with legacy app while integrating into Vite asset pipeline.","notes":"-"},
    {"day":"Day 2","activities":"Port SEMARAK JavaScript (api.js, per-page modules) into resources/js/absensi/; configure Vite entry points.\n– Tools: Vite, JavaScript ES modules, VSCode","learning":"Integrating legacy vanilla JavaScript into a Vite-based build system.","notes":"-"},
    {"day":"Day 3","activities":"Create Absensi module migrations (semarak DB): 6 parity migrations with Schema::hasTable() guards for safe re-run on existing DBs.\n– Tools: Laravel migrations, DB::connection('semarak')","learning":"Cross-connection migrations; idempotent schema guards for production safety.","notes":"-"},
    {"day":"Day 4","activities":"Implement GraduationService: luluskan() with cross-database transaction, compensation logic, one-time password generation.\n– Tools: Laravel DB::transaction, Str::password(), Log::info/error","learning":"Compensating transaction pattern for cross-database atomicity; secure one-time credential delivery.","notes":"-"},
    {"day":"Day 5","activities":"Build AnakResource in Filament admin panel: list, create, edit, delete with cross-database eager loading and schema guard.\n– Tools: Filament Resources, SchemaGuard, VSCode","learning":"Integrating cross-database Eloquent resources into Filament; deferred loading for performance.","notes":"Sprint review."},
])

# Week 11
add_weekly_table(doc, 11, [
    {"day":"Day 1","activities":"Implement ImportService: parseExcel (PhpSpreadsheet) and parseWord (PhpWord) parsers with dynamic header detection.\n– Tools: PhpSpreadsheet, PhpWord, VSCode","learning":"Library-based file parsing; adapting to multiple file template formats dynamically.","notes":"-"},
    {"day":"Day 2","activities":"Implement ImportService validation: MODE_BINAAN vs. MODE_KLIEN, duplicate detection (multi-field identity), max 2000-row limit.\n– Tools: VSCode, PHPUnit","learning":"Real-world data validation design: multi-field uniqueness, DoS protection via row limits.","notes":"-"},
    {"day":"Day 3","activities":"Implement ImportBinaanService: cross-database account provisioning (anak in semarak DB, Draft user in app DB, linked via anak_id).\n– Tools: DB::transaction (dual connection), VSCode","learning":"Cross-database record creation with explicit foreign-key management (no SQL FK across DBs).","notes":"-"},
    {"day":"Day 4","activities":"Build ImportData Filament page: file upload, mode selection, preview table (25 rows/page), confirm and execute import.\n– Tools: Filament Pages, Livewire, VSCode","learning":"Building complex multi-step Filament pages with Livewire state management.","notes":"-"},
    {"day":"Day 5","activities":"Build admin dashboard widgets: AdminStatsOverview (user counts), BinaanPerAngkatanChart, PertumbuhanAkunChart, PerluTindakanWidget.\n– Tools: Filament Widgets, Charts, VSCode","learning":"Building Filament chart widgets with cross-database aggregation queries.","notes":"Weekly review."},
])

# Week 12
add_weekly_table(doc, 12, [
    {"day":"Day 1","activities":"Build public-facing HomeController: landing page with program list, alumni directory, institution information.\n– Tools: Laravel controller, Blade views, VSCode","learning":"Public front-end controller patterns in Laravel; slug-based routing for profile pages.","notes":"-"},
    {"day":"Day 2","activities":"Build UserProfileController (public profile by slug), SewingProgramController, SocialGuidanceProgramController, ProgramDetailController.\n– Tools: Laravel routing (whereIn constraint), Blade, VSCode","learning":"Route parameter validation using whereIn(); public resource controllers.","notes":"-"},
    {"day":"Day 3","activities":"Write 46 Absensi API oracle fixture tests: capture native SEMARAK responses, compare byte-by-byte against Laravel implementation.\n– Tools: PHPUnit, PHP HTTP test client, fixture JSON files","learning":"Oracle-based testing methodology; byte-exact API parity verification strategy.","notes":"-"},
    {"day":"Day 4","activities":"Write Graduation feature tests (GraduationOnboardingTest, PengamanSkemaFase2Test): test luluskan() workflow, compensation on failure, schema guard bypass.\n– Tools: PHPUnit, Laravel testing helpers","learning":"Testing complex multi-service workflows with rollback scenarios.","notes":"-"},
    {"day":"Day 5","activities":"Write import feature tests (ImportBinaanTest, ImportPreviewTableTest): validate both import modes, duplicate detection, Word/Excel format parsing.\n– Tools: PHPUnit, test fixture Excel/Word files","learning":"File-based feature testing with real fixture files for robust parser validation.","notes":"Monthly review preparation."},
])

add_monthly_summary(doc, 3,
    q1="Principal activities: completed UserResource with work experience management; built full Absensi (SEMARAK) module with 6 pages, 11 API actions, 7 services, and cross-database Eloquent models; implemented GraduationService; built ImportService (Excel + Word, 2 modes); built ImportData Filament page; built public front-end controllers; wrote oracle parity tests and graduation/import feature tests.",
    q2="Learned cross-database Eloquent patterns, compensating transaction design, file-format adaptive parsing, oracle-based testing methodology, and Filament chart widget development. Practiced porting legacy PHP business logic to modern service classes while maintaining behavioral parity.",
    q3="Challenge: Cross-database eager loading between semarak.anak and app.users caused N+1 queries and connection errors. Solution: Used separate queries and in-memory mapping; implemented SchemaGuard to disable cross-database features gracefully when migrations not yet applied."
)
add_signature_block(doc)

doc.add_page_break()

# ─── MONTH 4: HARDENING, DEPLOYMENT & REPORTING (Weeks 13–16) ───────────────

add_monthly_header(doc,
    month_num=4,
    name="Kholis Kamaluddin Wahib",
    job_title="Intern (Backend Developer)",
    company="UPTD Pusat Pelayanan Sosial Griya Bina Remaja (PPSGBR) — Dinas Sosial Prov. Jawa Barat",
    department="Information Technology / Administration",
    supervisor="Bagas Adiantyo Putra, S.Tr.Sos.",
    sup_title="On-Site Supervisor",
    work_hours="[FILL: e.g., 08:00–16:00 WIB (40 hrs/week)]",
    period="Month 4 — Weeks 13–16: [FILL: DD/MM/2026] – [FILL: DD/MM/2026]"
)

# Week 13
add_weekly_table(doc, 13, [
    {"day":"Day 1","activities":"Implement SecurityHeaders middleware: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy (frame-ancestors, font-src 'self', form-action).\n– Tools: Laravel middleware artisan, VSCode","learning":"Web security headers: purpose and configuration of each header; CSP directives for font and form-action control.","notes":"-"},
    {"day":"Day 2","activities":"Replace CDN Inter font with self-hosted Inter v4.1 (InterVariable.woff2): update @font-face in _fonts.scss, remove external link tags, update CSP.\n– Tools: VSCode, SCSS, Vite, bash (font file placement)","learning":"Eliminating third-party CDN dependencies for security (CSP compliance) and privacy.","notes":"-"},
    {"day":"Day 3","activities":"Implement session security: AuthenticateSession middleware, session regeneration on login, session invalidation on logout, SESSION_SECURE_COOKIE config.\n– Tools: Laravel session docs, AppServiceProvider, VSCode","learning":"Session fixation and hijacking prevention; HTTPS session security configuration.","notes":"-"},
    {"day":"Day 4","activities":"Add anti-caching headers to GET /login: Cache-Control: no-store, Pragma: no-cache; configure robots meta noindex/nofollow.\n– Tools: LoginController, Blade layout, VSCode","learning":"Preventing browser caching of auth pages; search engine exclusion for login pages.","notes":"-"},
    {"day":"Day 5","activities":"Write security feature tests: LoginSecurityTest, SecurityHeadersTest, PanelAuthorizationTest, AccountStatusLoginTest, MassAssignmentTest.\n– Tools: PHPUnit, Laravel testing helpers","learning":"Testing security controls programmatically; assertion patterns for HTTP headers and status codes.","notes":"Weekly review."},
])

# Week 14
add_weekly_table(doc, 14, [
    {"day":"Day 1","activities":"Add Phase 2 database migrations: users.created_by (self-referential FK), users.anak_id (cross-DB link), users.graduated_at (alumni timestamp).\n– Tools: Laravel artisan make:migration, VSCode","learning":"Phased migration design; self-referential foreign keys; cross-database link columns.","notes":"-"},
    {"day":"Day 2","activities":"Implement SchemaGuard class: cached schema introspection methods (usersPunyaTautanAnak, usersPunyaTanggalLulus, usersPunyaKolomAlumni) for graceful feature degradation.\n– Tools: Laravel Schema::hasColumn(), Cache, VSCode","learning":"Runtime schema detection for safe incremental deployment; lazy feature activation pattern.","notes":"-"},
    {"day":"Day 3","activities":"Add Phase 2 features to AnakResource: Akun Klien column with cross-database eager loading, 'Luluskan & Aktifkan Akun Klien' action guarded by SchemaGuard.\n– Tools: Filament Resources, SchemaGuard, VSCode","learning":"Integrating schema guards into Filament UI; deferred table loading for performance.","notes":"-"},
    {"day":"Day 4","activities":"Configure Docker deployment: author Dockerfile (PHP 8.4-CLI base, extensions install, Composer, app copy, artisan optimize); write compose.yml with volume mounts.\n– Tools: Docker, Dockerfile syntax, compose.yml","learning":"Docker multi-step build optimization; layer caching for Composer dependencies; health check configuration.","notes":"-"},
    {"day":"Day 5","activities":"Test Docker image locally: build, run, verify health check, test all endpoints, verify SQLite volume persistence.\n– Tools: Docker Desktop, browser, curl","learning":"Container testing methodology; volume mount behavior for SQLite files.","notes":"Docker image delivered to supervisor."},
])

# Week 15
add_weekly_table(doc, 15, [
    {"day":"Day 1","activities":"User Acceptance Testing (UAT) with UPTD PPSGBR administrative staff: test all admin workflows (user import, profile management, absensi input, graduation).\n– Tools: Browser, test accounts, UAT checklist","learning":"Conducting UAT with real end users; documenting usability issues and bug reports.","notes":"UAT session with 2 staff members."},
    {"day":"Day 2","activities":"Fix UAT-identified bugs: narrow-column display issues in import preview table, absensi pagination edge case, login error message clarity.\n– Tools: VSCode, Filament, Blade, Chrome DevTools","learning":"Bug triage and prioritization from user-reported issues; front-end layout debugging.","notes":"-"},
    {"day":"Day 3","activities":"Deploy PENDEKAR1721 to institution server: push Docker image to local registry (192.168.200.110:8080), configure production.env, run container.\n– Tools: Docker, local registry, SSH, production.env","learning":"Production deployment workflow; environment configuration management; container registry usage.","notes":"Deployment to production server."},
    {"day":"Day 4","activities":"Write deployment documentation (docs/ABSENSI-DEPLOY.md): step-by-step guide for running migrations, building assets, and restarting Docker container.\n– Tools: Markdown, VSCode","learning":"Operations documentation for handover: writing clear runbooks for non-developer administrators.","notes":"-"},
    {"day":"Day 5","activities":"Conduct system handover session with supervisor: walkthrough of admin panel, absensi module, import process, and graduation workflow.\n– Tools: Browser, documentation, demo accounts","learning":"Formal system handover; communicating technical context to administrative users.","notes":"System handover meeting."},
])

# Week 16
add_weekly_table(doc, 16, [
    {"day":"Day 1","activities":"Run final security audit: composer audit (zero vulnerabilities reported), review .env production settings, verify BCRYPT_ROUNDS=12.\n– Tools: Composer audit, VSCode, .env review","learning":"Systematic pre-launch security checklist execution; dependency vulnerability scanning.","notes":"-"},
    {"day":"Day 2","activities":"Final bug fixes: resolve remaining minor issues from production testing; run full test suite (php artisan test) — all tests pass.\n– Tools: php artisan test, PHPUnit, VSCode","learning":"Final quality assurance; zero-defect deployment discipline.","notes":"-"},
    {"day":"Day 3","activities":"Draft and revise Chapters I–III of Internship Final Report.\n– Tools: Microsoft Word, google Docs, university handbook","learning":"Academic technical writing; structuring a formal internship report per handbook guidelines.","notes":"-"},
    {"day":"Day 4","activities":"Draft and revise Chapters IV–V of Internship Final Report; compile References list; prepare Appendices.\n– Tools: Microsoft Word, Google Docs","learning":"Reflective evaluation writing; APA reference formatting for technical documentation.","notes":"-"},
    {"day":"Day 5","activities":"Final review of Internship Final Report; submit to academic supervisor; internship closing ceremony/farewell.\n– Tools: Microsoft Word, email","learning":"Completing formal academic and institutional obligations; reflecting on 16-week learning journey.","notes":"Internship closing ceremony. Last day."},
])

add_monthly_summary(doc, 4,
    q1="Principal activities: implemented security headers, self-hosted fonts, session security, anti-caching; added Phase 2 migrations (users.anak_id, graduated_at, created_by); implemented SchemaGuard; Dockerized the application; conducted User Acceptance Testing; fixed UAT bugs; deployed to production server; wrote deployment and handover documentation; completed internship final report.",
    q2="Learned web security header configuration and CSP directives; Docker production deployment; UAT methodology; SchemaGuard pattern for safe incremental feature activation; formal technical documentation and academic report writing. Gained confidence in full-cycle software delivery from requirements to production.",
    q3="Challenge: Ensuring Docker image worked on the institution's local server registry with correct PHP version (8.4.1+) and production environment variables. Solution: Added PHP version assertion in Dockerfile; documented all required production.env variables; tested full image lifecycle before handover. Challenge: Resolving Word document content control (dropdown) values not being read by PhpWord. Solution: Implemented KontrolKontenWord helper to flatten content controls before parsing."
)
add_signature_block(doc)

doc.add_page_break()

# ════════════════════════════════════════════════════════════════════════════
# OTHER APPENDICES NOTICE
# ════════════════════════════════════════════════════════════════════════════
p = doc.add_paragraph()
r = p.add_run("SUPPORTING APPENDICES")
set_font(r, size_pt=16, bold=True)
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
para_spacing(p, before=0, after=12, line=1.0)

appendix_items = [
    ("Appendix A", "Curriculum Vitae (CV)"),
    ("Appendix B", "Cover Letter / Application Letter"),
    ("Appendix C", "LinkedIn Profile"),
    ("Appendix D", "Endorsement Letter from UPTD PPSGBR"),
    ("Appendix E", "Internship Agreement Form"),
    ("Appendix F", "Performance Evaluation (On-Site)"),
    ("Appendix G", "Internship Job Description — Backend Developer at UPTD PPSGBR\n[Updated: role is Backend Developer for PENDEKAR1721; responsibilities: database design, API development, authentication/authorization, service layer development, automated testing, Docker deployment, documentation]"),
    ("Appendix H", "Summary Attendance Record"),
    ("Appendix I", "Declaration of Originality"),
]
for label, desc in appendix_items:
    p = doc.add_paragraph()
    r1 = p.add_run(label + ": ")
    set_font(r1, size_pt=12, bold=True)
    r2 = p.add_run(desc)
    set_font(r2, size_pt=12)
    para_spacing(p, before=0, after=8, line=1.5)

add_body(doc, "[NOTE: Attach original scanned/signed documents from LAPORAN MAGANG - KHOLIS.docx for Appendices A–I. Do not alter signatures, scores, attendance data, or scanned images.]")

# ─── SAVE ────────────────────────────────────────────────────────────────────
doc.save(OUT_PATH)
print(f"✅  Saved: {OUT_PATH}")
print()
print("=== CHANGE LOG / PLACEHOLDERS TO FILL ===")
print()
print("REMOVED from old report:")
print("  - All content related to PT Pakoakuina, SQL Server, OEE, MQTT, Smart Factory,")
print("    trolley/melting/casting, Department 4.0, 29 weeks, UI/UX Analyst role.")
print("  - Broken/empty tables in Chapter III (old 3.5).")
print("  - 'PREFERANCE' typo → PREFACE")
print("  - 'INTERSHIP' typo → INTERNSHIP")
print("  - 'COMPANY PROFIL' typo → COMPANY PROFILE")
print("  - 'TABLE OF CONTENT' typo → TABLE OF CONTENTS")
print("  - 'Karawang, 2026' placeholder → 'Lembang, December 2026'")
print()
print("WRITTEN (new content):")
print("  - Full 5-chapter report rewritten for Backend Developer / PENDEKAR1721 context.")
print("  - 16-week Monthly Report (Months 1–4, Weeks 1–16, 5 days/week) per Handbook Appendix 6.")
print("  - ERD summary table, feature table, challenges table.")
print("  - References list (APA, 11 sources).")
print()
print("PLACEHOLDERS TO FILL (by student):")
print("  [FILL: DD/MM/YYYY] — exact internship start and end dates (cover, approval page, monthly headers)")
print("  [FILL: work hours] — official daily work hours (e.g., 08:00–16:00 WIB)")
print("  [CONFIRM WITH KHOLIS]: year UPTD PPSGBR established, official Vision/Mission text, org chart data")
print("  [INSERT LOGO: Horizon University Indonesia] — cover page logo")
print("  [INSERT SCAN: Letter of Internship Completion] — keep original scanned letter")
print("  [INSERT SCREENSHOT: ...] — 8 system screenshots listed in Section 3.5.4")
print("  [INSERT ORGANIZATIONAL CHART IMAGE] — Chapter II Section 2.3")
print()
print("HANDBOOK RULES NOT FULLY SATISFIED (and why):")
print("  1. Cover page DARK BLUE color theme: Implemented via dark-blue colored text.")
print("     Full dark-blue page background requires complex XML shading not in python-docx;")
print("     recommend manually setting page color in Word: Design > Page Color > Dark Blue.")
print("  2. Auto-generated TOC: TOC field inserted; must right-click > Update Field in Word.")
print("  3. Letter of Completion and scanned appendices: placeholders added; student must")
print("     paste actual scanned images from the original DOCX manually.")
print("  4. Right margin = 1.5\" per handbook: set on all sections via python-docx.")
print("     NOTE: handbook says Left=1.5\", Right=1\" in some versions; the prompt specifies")
print("     Right=1.5\" which is applied. Verify against your handbook page.")
print("  5. Body max 25 pages: achieved by concise writing; verify after adding screenshots.")
print("  6. Page numbers: Arabic page numbers set in footer from Chapter I onward.")
print("     Cover/approval/TOC pages should use Roman numerals — manually add in Word if required.")
