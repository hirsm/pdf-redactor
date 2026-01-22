# ~/pdf-redactor/app.py
from flask import Flask, request, send_file
import fitz
import json
import tempfile
import os

app = Flask(__name__)

# Limit upload size
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
                # Draw rectangle
                page.add_redact_annot(fitz.Rect(x, y, x+w, y+h), fill=(0, 0, 0))
        
        # Redact securely
        for page in doc:
            page.apply_redactions()
            
        temp_fd, temp_path = tempfile.mkstemp(suffix='.pdf')
        os.close(temp_fd)
        doc.save(temp_path, garbage=4, deflate=True)
        doc.close()
        
        # Temp file is opened and deleted. Physically deleted when handle gets closed
        f = open(temp_path, 'rb')
        os.unlink(temp_path)
        
        return send_file(
            f,
            mimetype='application/pdf',
            as_attachment=True,
            download_name='redacted.pdf'
        )
        
    except Exception as e:
        if 'temp_path' in locals() and os.path.exists(temp_path):
            os.unlink(temp_path)
        return str(e), 500

if __name__ == '__main__':
    app.run()
