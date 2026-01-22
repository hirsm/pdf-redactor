# ~/pdf-redactor/app.py
from flask import Flask, request, send_file
import fitz
import json
import tempfile
import os

app = Flask(__name__)

# Maximale Upload-Größe begrenzen
app.config['MAX_CONTENT_LENGTH'] = 200 * 1024 * 1024  # 200 MB

@app.route('/redact', methods=['POST'])
def redact_pdf():
    try:
        if 'pdf' not in request.files or 'redactions' not in request.form:
            return "Fehlende Daten", 400
        
        uploaded_file = request.files['pdf']
        redactions = json.loads(request.form['redactions'])
        
        doc = fitz.open(stream=uploaded_file.read(), filetype="pdf")
        
        for item in redactions:
            page_idx = item.get('page', 1) - 1
            if 0 <= page_idx < len(doc):
                page = doc[page_idx]
                x, y, w, h = item['rect']
                # Rechteck zeichnen
                page.add_redact_annot(fitz.Rect(x, y, x+w, y+h), fill=(0, 0, 0))
        
        # Sicher anwenden
        for page in doc:
            page.apply_redactions()
            
        output_stream = io.BytesIO()
        doc.save(output_stream, garbage=4, deflate=True)
        output_stream.seek(0)
        
        return send_file(output_stream, as_attachment=True, download_name='result.pdf', mimetype='application/pdf')
        
    except Exception as e:
        return str(e), 500

if __name__ == '__main__':
    app.run()
