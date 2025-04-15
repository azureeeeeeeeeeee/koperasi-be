<!DOCTYPE html>
<html>
<head>
    <title>Kode OTP</title>
</head>
<body>
    <h2>Link verifikasi Anda</h2>
    <p>Klik Link berikut untuk verifikasi:</p>
    
    <div style="font-size: 24px; font-weight: bold; margin: 20px 0;">
        {{ $verificationUrl ?? 'URL tidak tersedia' }}
    </div>
    
    <p>Kode ini akan kadaluarsa dalam 24 Jam.</p>
    
    <footer>
        Terima kasih,<br>
        Tim {{ config('app.name') }}
    </footer>
</body>
</html>