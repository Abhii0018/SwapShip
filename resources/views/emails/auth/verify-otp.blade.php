<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
</head>
<body style="margin:0;padding:24px;background:#050505;color:#ffffff;font-family:Arial,sans-serif;">
    <div style="max-width:560px;margin:0 auto;background:#0d0d0d;border:1px solid #222;border-radius:12px;padding:20px;">
        <p style="margin:0 0 10px;">Hi {{ $name }},</p>
        <p style="margin:0 0 14px;color:#c6c6c6;">Use this OTP to verify your SwapShip account:</p>
        <div style="display:inline-block;padding:10px 16px;border-radius:10px;background:#BFFF00;color:#000;font-size:28px;letter-spacing:4px;font-weight:700;">
            {{ $otp }}
        </div>
        <p style="margin:14px 0 0;color:#9c9c9c;font-size:13px;">This code expires in 10 minutes.</p>
    </div>
</body>
</html>
