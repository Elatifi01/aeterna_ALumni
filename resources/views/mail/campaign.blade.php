<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f1f3; font-family:Inter,'Segoe UI',system-ui,sans-serif; color:#15171c;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f1f3; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border:1px solid #e7e7ea; border-radius:12px;">
                    <tr>
                        <td style="padding:32px 32px 24px 32px; text-align:center; border-bottom:1px solid #e7e7ea;">
                            <div style="font-size:22px; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:#16375a;">
                                Aeterna
                            </div>
                            <div style="margin-top:6px; font-size:13px; color:#5a5f6b;">
                                {{ $organizationName }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px 0; font-size:14px; color:#5a5f6b;">
                                Bonjour {{ $alumni->first_name }},
                            </p>

                            <div style="font-size:14px; line-height:22px; color:#15171c; white-space:pre-line;">{{ $campaign->content }}</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px 28px 32px; border-top:1px solid #e7e7ea;">
                            <p style="margin:0; font-size:12px; line-height:18px; color:#8b909b;">
                                Vous recevez ce message en tant qu'ancien etudiant de
                                {{ $organizationName }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
