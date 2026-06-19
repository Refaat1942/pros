import base64
import re
import urllib.request

import markdown

DOCS_DIR = r"D:\Heard\prosthetics\docs"
MD_PATH = DOCS_DIR + r"\new_analysis.md"
HTML_PATH = DOCS_DIR + r"\new_analysis_print.html"

CAIRO_FILES = {
    400: "https://fonts.gstatic.com/s/cairo/v28/SLXgc1nY6HkvalIhTp2mxdt0UX8.woff2",
    600: "https://fonts.gstatic.com/s/cairo/v28/SLXgc1nY6HkvalIhTp2mxdt0UX8.woff2",
    700: "https://fonts.gstatic.com/s/cairo/v28/SLXgc1nY6HkvalIhTp2mxdt0UX8.woff2",
}


def fetch_cairo_css():
    """Get the official Google Fonts CSS, then inline every woff2 as base64."""
    req = urllib.request.Request(
        "https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;900&display=swap",
        headers={"User-Agent": "Mozilla/5.0"},
    )
    css = urllib.request.urlopen(req, timeout=30).read().decode("utf-8")

    urls = sorted(set(re.findall(r"url\((https://[^)]+\.woff2)\)", css)))
    cache = {}
    for u in urls:
        data = urllib.request.urlopen(u, timeout=30).read()
        b64 = base64.b64encode(data).decode("ascii")
        cache[u] = f"data:font/woff2;base64,{b64}"

    for u, datauri in cache.items():
        css = css.replace(u, datauri)
    return css


def main():
    with open(MD_PATH, encoding="utf-8") as f:
        md_text = f.read()

    body = markdown.markdown(
        md_text,
        extensions=["tables", "fenced_code", "toc", "sane_lists"],
    )

    font_css = fetch_cairo_css()

    html = f"""<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>نظام الإدارة المتكامل — Smart Prosthetics ERP</title>
<style>
{font_css}

* {{ box-sizing: border-box; }}

html, body {{
  font-family: 'Cairo', sans-serif;
  direction: rtl;
  text-align: right;
  color: #1f2933;
  line-height: 1.9;
  font-size: 13px;
  margin: 0;
}}

body {{ padding: 0 6px; }}

h1, h2, h3, h4 {{
  font-family: 'Cairo', sans-serif;
  font-weight: 700;
  color: #0f3d57;
  line-height: 1.5;
}}

h1 {{
  font-size: 26px;
  text-align: center;
  border-bottom: 3px solid #0f6e8c;
  padding-bottom: 14px;
  margin-bottom: 6px;
}}

h2 {{
  font-size: 20px;
  margin-top: 26px;
  padding: 8px 14px;
  background: linear-gradient(90deg, #0f6e8c, #0f3d57);
  color: #fff;
  border-radius: 8px;
  page-break-after: avoid;
}}

h3 {{
  font-size: 16px;
  margin-top: 20px;
  color: #0f6e8c;
  border-right: 4px solid #0f6e8c;
  padding-right: 10px;
  page-break-after: avoid;
}}

p {{ margin: 8px 0; }}

em {{ color: #62748a; font-style: normal; font-weight: 600; }}

a {{ color: #0f6e8c; text-decoration: none; }}

table {{
  border-collapse: collapse;
  width: 100%;
  margin: 14px 0;
  font-size: 12px;
  page-break-inside: avoid;
}}

th, td {{
  border: 1px solid #cbd5e1;
  padding: 8px 10px;
  text-align: right;
  vertical-align: top;
}}

th {{
  background: #0f3d57;
  color: #fff;
  font-weight: 600;
}}

tr:nth-child(even) td {{ background: #f1f5f9; }}

blockquote {{
  margin: 12px 0;
  padding: 10px 16px;
  background: #eef6fb;
  border-right: 4px solid #0f6e8c;
  border-radius: 6px;
  color: #243b53;
}}

code {{
  font-family: 'Cairo', monospace;
  background: #eef2f7;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
}}

pre {{
  background: #0f3d57;
  color: #e6f1f7;
  padding: 14px;
  border-radius: 8px;
  overflow-x: auto;
  direction: ltr;
  text-align: left;
  page-break-inside: avoid;
}}

pre code {{ background: transparent; color: inherit; padding: 0; }}

ul, ol {{ padding-right: 22px; padding-left: 0; }}
li {{ margin: 4px 0; }}

hr {{
  border: none;
  border-top: 1px solid #cbd5e1;
  margin: 22px 0;
}}

strong {{ color: #0f3d57; }}

@page {{
  size: A4;
  margin: 16mm 14mm;
}}
</style>
</head>
<body>
{body}
</body>
</html>
"""

    with open(HTML_PATH, "w", encoding="utf-8") as f:
        f.write(html)
    print("HTML written:", HTML_PATH)


if __name__ == "__main__":
    main()
