<div style="font-family:Arial,sans-serif;background:#f6f8fb;padding:24px;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;border:1px solid #e8ecf2;">
        <h2 style="margin:0 0 8px;color:#111827;">SwapShip Password Reset</h2>
        <p style="margin:0 0 16px;color:#374151;">Hi {{ $name ?: 'there' }}, use this OTP to reset your password:</p>
        <div style="font-size:28px;letter-spacing:6px;font-weight:700;color:#111827;background:#f3f6fb;border:1px solid #dbe3ee;border-radius:10px;padding:12px 14px;display:inline-block;">
            {{ $otp }}
        </div>
        <p style="margin:16px 0 0;color:#6b7280;">This OTP expires in {{ $expiresInMinutes }} minutes.</p>
        <p style="margin:8px 0 0;color:#6b7280;">If you did not request this, you can ignore this email.</p>
    </div>
</div>
