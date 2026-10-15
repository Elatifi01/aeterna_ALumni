<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $demo->subjectLabel() }}</title>
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
                                {{ $demo->subjectLabel() }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#15171c;">
                                @switch($demo->subject)
                                    @case('custom')
                                        <strong>{{ $demo->name }}</strong> souhaite une formule sur mesure pour
                                        <strong>{{ $demo->organization }}</strong>.
                                        @break
                                    @case('demo')
                                        <strong>{{ $demo->name }}</strong> souhaite découvrir Aeterna pour
                                        <strong>{{ $demo->organization }}</strong>.
                                        @break
                                    @default
                                        <strong>{{ $demo->name }}</strong>, de <strong>{{ $demo->organization }}</strong>,
                                        vous a écrit depuis le site.
                                @endswitch
                                Répondez simplement à cet e-mail pour le recontacter.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f8; border:1px solid #e7e7ea; border-radius:8px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        @foreach ([
                                            'Objet' => $demo->subjectLabel(),
                                            'Nom' => $demo->name,
                                            'Établissement' => $demo->organization,
                                            'E-mail' => $demo->email,
                                            'Téléphone' => $demo->phone ?: '—',
                                            'Reçue le' => $demo->created_at?->timezone(config('app.timezone'))->format('d/m/Y à H:i'),
                                        ] as $label => $value)
                                            <div style="{{ $loop->first ? '' : 'margin-top:14px;' }} font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#8b909b;">
                                                {{ $label }}
                                            </div>
                                            <div style="margin-top:4px; font-size:15px; color:#15171c;">
                                                @if ($label === 'E-mail')
                                                    <a href="mailto:{{ $value }}" style="color:#16375a;">{{ $value }}</a>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </div>
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($demo->message)
                        <tr>
                            <td style="padding:16px 32px 8px 32px;">
                                <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#8b909b;">
                                    Message
                                </div>
                                <p style="margin:6px 0 0 0; font-size:14px; line-height:22px; color:#15171c; white-space:pre-line;">{{ $demo->message }}</p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:24px 32px 32px 32px;">
                            <a href="mailto:{{ $demo->email }}?subject={{ rawurlencode('Votre démo Aeterna') }}"
                               style="display:inline-block; background-color:#0c1e33; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 22px; border-radius:10px;">
                                Répondre à {{ $demo->name }}
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
