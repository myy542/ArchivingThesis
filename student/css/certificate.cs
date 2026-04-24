body {
    font-family: 'Times New Roman', Times, serif;
    background: #f0f0f0;
    margin: 0;
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

body.dark-mode {
    background: #1a1a1a;
}

.certificate-wrapper {
    max-width: 900px;
    margin: 0 auto;
}

.certificate {
    background: white;
    border: 20px solid #FE4853;
    padding: 40px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

body.dark-mode .certificate {
    background: #2d2d2d;
    border-color: #dc2626;
}

.certificate:before {
    content: "";
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    bottom: 10px;
    border: 2px solid #732529;
    pointer-events: none;
}

.header {
    text-align: center;
    margin-bottom: 40px;
}

.header h1 {
    color: #FE4853;
    font-size: 48px;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 5px;
}

body.dark-mode .header h1 {
    color: #fecaca;
}

.header h2 {
    color: #732529;
    font-size: 24px;
    margin: 10px 0 0;
    font-style: italic;
}

body.dark-mode .header h2 {
    color: #fecaca;
}

.content {
    text-align: center;
    margin: 50px 0;
}

.content p {
    font-size: 18px;
    color: #333;
    line-height: 2;
}

body.dark-mode .content p {
    color: #e0e0e0;
}

.student-name {
    font-size: 36px;
    color: #FE4853;
    font-weight: bold;
    margin: 20px 0;
    text-transform: uppercase;
    border-bottom: 2px solid #732529;
    display: inline-block;
    padding-bottom: 10px;
}

body.dark-mode .student-name {
    color: #fecaca;
    border-bottom-color: #dc2626;
}

.thesis-title {
    font-size: 24px;
    color: #732529;
    font-style: italic;
    margin: 20px 0;
}

body.dark-mode .thesis-title {
    color: #fecaca;
}

.date {
    font-size: 18px;
    color: #666;
    margin: 30px 0;
}

body.dark-mode .date {
    color: #94a3b8;
}

.signature {
    margin-top: 60px;
    display: flex;
    justify-content: space-between;
}

.signature-line {
    width: 200px;
    border-top: 2px solid #333;
    margin-top: 40px;
}

body.dark-mode .signature-line {
    border-top-color: #e0e0e0;
}

.signature-item {
    text-align: center;
}

.signature-item p {
    margin: 5px 0;
    color: #666;
}

body.dark-mode .signature-item p {
    color: #94a3b8;
}

.seal {
    position: absolute;
    bottom: 50px;
    right: 50px;
    width: 100px;
    height: 100px;
    border: 3px solid #FE4853;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: rotate(-15deg);
}

.seal p {
    color: #FE4853;
    font-size: 14px;
    font-weight: bold;
    text-align: center;
    line-height: 1.4;
}

body.dark-mode .seal {
    border-color: #fecaca;
}

body.dark-mode .seal p {
    color: #fecaca;
}

.footer {
    text-align: center;
    margin-top: 40px;
    color: #999;
    font-size: 12px;
}

body.dark-mode .footer {
    color: #6b7280;
}

.actions {
    text-align: center;
    margin-top: 30px;
}

.btn-print {
    background: #FE4853;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}

.btn-print:hover {
    background: #732529;
    transform: translateY(-2px);
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #666;
    text-decoration: none;
}

.back-link:hover {
    color: #FE4853;
}

@media print {
    body {
        background: white;
        padding: 0;
    }
    .actions, .back-link {
        display: none;
    }
    .certificate {
        border: 20px solid #FE4853;
        box-shadow: none;
    }
}