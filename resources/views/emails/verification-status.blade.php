<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family: Arial, Helvetica, sans-serif; color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:14px; overflow:hidden; max-width:480px; width:100%;">
                    {{-- Header --}}
                    <tr>
                        <td style="background:#1a3a3a; padding:28px 32px; text-align:center;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold; letter-spacing:0.5px;">Picklepass</span>
                        </td>
                    </tr>

                    {{-- Status banner --}}
                    <tr>
                        <td style="padding:32px 32px 8px 32px; text-align:center;">
                            @if($approved)
                                <div style="display:inline-block; width:64px; height:64px; line-height:64px; border-radius:50%; background:#10B981; color:#ffffff; font-size:32px;">&#10003;</div>
                                <h1 style="font-size:22px; margin:20px 0 8px;">Account Verified</h1>
                            @else
                                <div style="display:inline-block; width:64px; height:64px; line-height:64px; border-radius:50%; background:#e74c3c; color:#ffffff; font-size:32px;">&#10005;</div>
                                <h1 style="font-size:22px; margin:20px 0 8px;">Verification Not Approved</h1>
                            @endif
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:8px 32px 32px 32px; font-size:15px; line-height:1.6; color:#334155;">
                            <p style="margin:0 0 16px;">Hi {{ $name }},</p>
                            @if($approved)
                                <p style="margin:0 0 16px;">
                                    Great news! Your account has been <strong>verified</strong>. You now have full access to
                                    manage your <strong>Courts</strong> and <strong>Events</strong> in the Picklepass app.
                                </p>
                                <p style="margin:0 0 16px;">Open the app to get started.</p>
                            @else
                                <p style="margin:0 0 16px;">
                                    We're sorry, but your verification request was <strong>not approved</strong> at this time.
                                    This usually happens when the submitted ID, court photos, or documents are unclear or incomplete.
                                </p>
                                <p style="margin:0 0 16px;">
                                    You can resubmit your information anytime under
                                    <strong>Settings &rarr; Verification Account</strong> in the app.
                                </p>
                            @endif
                            <p style="margin:24px 0 0; color:#64748b; font-size:13px;">— The Picklepass Team</p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f8fafc; padding:18px 32px; text-align:center; font-size:12px; color:#94a3b8;">
                            This is an automated message. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
