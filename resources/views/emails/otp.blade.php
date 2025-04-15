<!DOCTYPE html>
<html>
<head>
    <title>Kode OTP</title>
</head>
<body>
    <h2>Kode OTP Anda</h2>
    <p>Gunakan kode berikut untuk verifikasi:</p>
    
    <div style="font-size: 24px; font-weight: bold; margin: 20px 0;">
        {{ $otpCode ?? 'Kode tidak tersedia' }}
    </div>
    
    <p>Kode ini akan kadaluarsa dalam 5 menit.</p>
    
    <footer>
        Terima kasih,<br>
        Tim {{ config('app.name') }}
    </footer>
</body>
</html>