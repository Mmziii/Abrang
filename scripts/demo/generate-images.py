"""Generate the Abrang demo product images (original CC0 drawings).

Every demo product gets ONE image that matches its name: a pen drawing for the
pen, a notebook for the notebook, and so on. The drawings are simple geometric
shapes painted with the brand palette, so the files stay small (~9 KB each),
carry no third-party license and need no download.

Optional tooling (development machine only): pillow, fonttools, arabic-reshaper,
python-bidi. `scripts/demo.sh` itself needs none of these — it only copies the
already-generated PNGs from `scripts/demo/images/`.

Run from the repository root:

    python scripts/demo/generate-images.py
"""
from io import BytesIO
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from fontTools.ttLib import TTFont
import arabic_reshaper
from bidi.algorithm import get_display

OUT = Path(__file__).parent / 'images'
SIZE = 800

PAPER, INK, INDIGO = '#FBF8F3', '#2A2A2A', '#2B4C7E'
CORAL, TEAL, OCHRE, MIST = '#E8735A', '#3FA7A0', '#E9B949', '#E6EEF5'

_font = TTFont('wp-content/themes/abrang/assets/fonts/Vazirmatn-Regular.woff2')
_font.flavor = None
_buffer = BytesIO()
_font.save(_buffer)
LABEL_FONT = ImageFont.truetype(BytesIO(_buffer.getvalue()), 26)
LABEL = get_display(arabic_reshaper.reshape('نمونهٔ آزمایشی'))


def pen(color=INDIGO, accent=OCHRE):
    """A single capped pen standing on its nib."""
    def draw(d):
        x = 368
        d.rounded_rectangle((x, 165, x + 64, 545), radius=22, fill=color)
        d.rectangle((x, 215, x + 64, 240), fill=accent)
        d.line((x + 48, 175, x + 48, 290), fill=PAPER, width=8)
        d.rounded_rectangle((x + 74, 235, x + 90, 320), radius=8, fill=accent)
        d.polygon([(x, 545), (x + 64, 545), (x + 32, 620)], fill=INK)
    return draw


def pencil():
    """A mechanical pencil with a push button and a lead sleeve."""
    def draw(d):
        x = 370
        d.rounded_rectangle((x, 155, x + 60, 250), radius=16, fill=CORAL)
        d.rounded_rectangle((x, 240, x + 60, 480), radius=14, fill=TEAL)
        d.rectangle((x, 470, x + 60, 520), fill=MIST)
        d.polygon([(x + 14, 520), (x + 46, 520), (x + 30, 590)], fill=INK)
        d.rectangle((x + 27, 575, x + 33, 615), fill=INDIGO)
    return draw


def notebook():
    """A bound notebook with visible ruled pages."""
    def draw(d):
        d.rounded_rectangle((220, 165, 590, 615), radius=20, fill=INDIGO)
        d.rounded_rectangle((240, 175, 575, 600), radius=12, fill=PAPER)
        d.rectangle((240, 175, 285, 600), fill=TEAL)
        for y in range(260, 550, 45):
            d.line((315, y, 535, y), fill=MIST, width=5)
        for y in range(200, 580, 45):
            d.ellipse((218, y, 253, y + 15), fill=OCHRE)
    return draw


def planner():
    """A weekly planner page: seven columns above a ruled notes area."""
    def draw(d):
        d.rounded_rectangle((185, 160, 625, 620), radius=18, fill=PAPER, outline=INDIGO, width=6)
        d.rectangle((185, 160, 625, 230), fill=TEAL)
        for x in range(240, 610, 50):
            d.ellipse((x, 138, x + 30, 158), fill=OCHRE)
        for i in range(7):
            x = 205 + i * 58
            d.rectangle((x, 255, x + 44, 420), outline=MIST, width=5)
        for y in range(455, 590, 42):
            d.line((215, y, 595, y), fill=MIST, width=6)
    return draw


def paper_sheets():
    """A small stack of drawing paper with a folded corner."""
    def draw(d):
        d.polygon([(250, 235), (600, 205), (620, 555), (270, 585)], fill=MIST)
        d.polygon([(215, 205), (565, 205), (565, 605), (215, 605)], fill=PAPER, outline=INDIGO, width=6)
        d.polygon([(480, 205), (565, 205), (565, 290)], fill=MIST)
        for y in range(280, 540, 60):
            d.line((265, y, 515, y), fill=TEAL, width=6)
    return draw


def watercolor():
    """A watercolor tin with twelve pans and a wooden brush."""
    def draw(d):
        d.rounded_rectangle((155, 225, 645, 505), radius=30, fill=INDIGO)
        d.rounded_rectangle((175, 245, 625, 485), radius=20, fill=PAPER)
        for n, color in enumerate([INDIGO, CORAL, TEAL, OCHRE, INK, TEAL, OCHRE, CORAL,
                                   TEAL, INDIGO, CORAL, OCHRE]):
            x, y = 195 + (n % 4) * 106, 265 + (n // 4) * 74
            d.rounded_rectangle((x, y, x + 86, y + 62), radius=10, fill=color)
        d.line((220, 570, 570, 570), fill=OCHRE, width=18)
        d.polygon([(570, 559), (640, 570), (570, 581)], fill=INK)
    return draw


def brush():
    """A single round brush: wooden handle, metal ferrule, dark bristles."""
    def draw(d):
        d.line((400, 170, 400, 420), fill=OCHRE, width=26)
        d.rectangle((382, 420, 418, 480), fill=MIST, outline=INDIGO, width=4)
        d.polygon([(382, 480), (418, 480), (400, 640)], fill=INDIGO)
        d.line((400, 480, 400, 620), fill=TEAL, width=8)
    return draw


def pencil_case():
    """A zipped fabric pencil case."""
    def draw(d):
        d.rounded_rectangle((170, 275, 630, 515), radius=65, fill=TEAL)
        d.rounded_rectangle((185, 285, 615, 485), radius=55, outline=INDIGO, width=6)
        d.line((210, 340, 590, 340), fill=PAPER, width=9)
        for x in range(220, 580, 20):
            d.line((x, 328, x, 351), fill=INDIGO, width=4)
        d.rounded_rectangle((555, 335, 587, 385), radius=8, fill=OCHRE)
    return draw


def geometry():
    """A geometry set: set square, ruler and compass."""
    def draw(d):
        d.polygon([(170, 560), (170, 250), (480, 560)], outline=INDIGO, width=14)
        d.polygon([(205, 525), (205, 330), (400, 525)], outline=MIST, width=8)
        d.rounded_rectangle((500, 250, 630, 310), radius=12, fill=OCHRE)
        for x in range(515, 620, 22):
            d.line((x, 250, x, 272), fill=INK, width=5)
        d.line((565, 350, 520, 560), fill=TEAL, width=14)
        d.line((565, 350, 615, 560), fill=TEAL, width=14)
        d.ellipse((550, 335, 580, 365), fill=CORAL)
    return draw


def stapler():
    """A desk stapler seen from the side."""
    def draw(d):
        d.rounded_rectangle((185, 480, 615, 540), radius=18, fill=INK)
        d.rounded_rectangle((205, 380, 615, 445), radius=26, fill=INDIGO)
        d.rounded_rectangle((530, 385, 625, 445), radius=18, fill=CORAL)
        d.rectangle((215, 440, 585, 460), fill=MIST)
        d.rounded_rectangle((245, 300, 305, 385), radius=14, fill=OCHRE)
    return draw


def folder():
    """A document folder with a tab and a paper insert."""
    def draw(d):
        d.rounded_rectangle((170, 220, 360, 290), radius=18, fill=INDIGO)
        d.rounded_rectangle((170, 260, 635, 580), radius=20, fill=INDIGO)
        d.rectangle((205, 245, 590, 520), fill=PAPER)
        for y in range(290, 460, 40):
            d.line((245, y, 545, y), fill=MIST, width=7)
        d.polygon([(170, 370), (635, 370), (610, 600), (195, 600)], fill=TEAL)
        d.ellipse((385, 390, 415, 420), fill=OCHRE)
    return draw


def glue_stick():
    """A glue stick with its cap off to one side."""
    def draw(d):
        d.rounded_rectangle((330, 250, 470, 600), radius=20, fill=INDIGO)
        d.rectangle((330, 320, 470, 430), fill=PAPER)
        d.rounded_rectangle((350, 165, 450, 250), radius=16, fill=PAPER, outline=MIST, width=6)
        d.rounded_rectangle((510, 300, 630, 600), radius=24, fill=CORAL)
        d.rounded_rectangle((510, 250, 630, 310), radius=16, fill=OCHRE)
    return draw


IMAGES = {
    'pen': pen(),
    'pencil': pencil(),
    'notebook': notebook(),
    'planner': planner(),
    'paper-sheets': paper_sheets(),
    'watercolor': watercolor(),
    'brush': brush(),
    'pencil-case': pencil_case(),
    'geometry': geometry(),
    'stapler': stapler(),
    'folder': folder(),
    'glue-stick': glue_stick(),
}

if __name__ == '__main__':
    OUT.mkdir(parents=True, exist_ok=True)
    force = '--force' in __import__('sys').argv
    for name, draw in IMAGES.items():
        target = OUT / f'{name}.png'
        if target.exists() and not force:
            print(f'kept {name}.png')
            continue
        image = Image.new('RGB', (SIZE, SIZE), PAPER)
        canvas = ImageDraw.Draw(image)
        canvas.ellipse((80, 80, 720, 720), fill=MIST)
        draw(canvas)
        canvas.text((400, 730), LABEL, font=LABEL_FONT, anchor='mm', fill=INK)
        image.save(target, optimize=True)
        print(f'generated {name}.png ({target.stat().st_size} bytes)')
