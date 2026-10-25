"""Original CC0 product-like fixtures. Optional tooling: pillow, fonttools, brotli.
Run from repository root; production/demo.sh needs none of these dependencies.
"""
from io import BytesIO
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
from fontTools.ttLib import TTFont

font = TTFont('wp-content/themes/abrang/assets/fonts/Vazirmatn-Regular.woff2')
font.flavor = None
buffer = BytesIO()
font.save(buffer)
label_font = ImageFont.truetype(BytesIO(buffer.getvalue()), 26)
paper, ink, indigo, coral, teal, ochre, mist = '#FBF8F3', '#2A2A2A', '#2B4C7E', '#E8735A', '#3FA7A0', '#E9B949', '#E6EEF5'
for i in range(5):
    im = Image.new('RGB', (800, 800), paper)
    d = ImageDraw.Draw(im)
    d.ellipse((80, 80, 720, 720), fill=mist)
    if i == 0:  # A pair of capped pens with pointed nibs.
        for x, color in [(295, indigo), (425, teal)]:
            d.rounded_rectangle((x, 175, x+65, 555), radius=22, fill=color)
            d.rectangle((x, 220, x+65, 240), fill=ochre)
            d.line((x+48, 185, x+48, 290), fill=paper, width=8)
            d.polygon([(x, 555), (x+65, 555), (x+32, 620)], fill=ink)
    elif i == 1:  # Bound notebook with visible ruled pages.
        d.rounded_rectangle((220, 165, 590, 615), radius=20, fill=indigo)
        d.rounded_rectangle((240, 175, 575, 600), radius=12, fill=paper)
        d.rectangle((240, 175, 285, 600), fill=teal)
        for y in range(260, 550, 45): d.line((315, y, 535, y), fill=mist, width=5)
        for y in range(200, 580, 45): d.ellipse((218, y, 253, y+15), fill=ochre)
    elif i == 2:  # Watercolor tin and a wooden paintbrush.
        d.rounded_rectangle((155, 235, 645, 535), radius=30, fill=indigo)
        d.rounded_rectangle((175, 255, 625, 515), radius=20, fill=paper)
        for n, color in enumerate([indigo,coral,teal,ochre,ink,teal,ochre,coral]):
            x, y = 195 + (n % 4)*108, 280+(n//4)*120
            d.rounded_rectangle((x, y, x+82, y+88), radius=10, fill=color)
        d.line((220, 590, 570, 590), fill=ochre, width=18)
        d.polygon([(570,579),(640,590),(570,601)], fill=ink)
    elif i == 3:  # Zipped stationery case.
        d.rounded_rectangle((170, 275, 630, 515), radius=65, fill=teal)
        d.rounded_rectangle((185, 285, 615, 485), radius=55, outline=indigo, width=6)
        d.line((210, 340, 590, 340), fill=paper, width=9)
        for x in range(220,580,20): d.line((x,328,x,351),fill=indigo,width=4)
        d.rounded_rectangle((555,335,587,385),radius=8,fill=ochre)
    else:  # Document folder with tab and paper insert.
        d.rounded_rectangle((170, 220, 360, 290), radius=18, fill=indigo)
        d.rounded_rectangle((170, 260, 635, 580), radius=20, fill=indigo)
        d.rectangle((205, 245, 590, 520), fill=paper)
        for y in range(290, 460, 40): d.line((245,y,545,y),fill=mist,width=7)
        d.polygon([(170,370),(635,370),(610,600),(195,600)], fill=teal)
        d.ellipse((385,390,415,420),fill=ochre)
    d.text((400, 730), 'نمونهٔ آزمایشی', font=label_font, anchor='mm', fill=ink, direction='rtl')
    im.save(Path(__file__).parent / 'images' / f'{i}.png', optimize=True)
