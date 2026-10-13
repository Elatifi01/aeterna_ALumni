<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre acces au portail alumni</title>
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
                        <td style="padding:32px 32px 8px 32px;">
                            <p style="margin:0 0 16px 0; font-size:14px; color:#5a5f6b;">
                                Bonjour {{ $alumni->first_name }},
                            </p>

                            <p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#15171c;">
                                {{ $organizationName }} vous ouvre un acces a son portail alumni.
                                Vous pourrez y consulter les evenements et vous y inscrire,
                                retrouver les messages qui vous sont adresses et parcourir
                                les offres d'emploi.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:8px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f8; border:1px solid #e7e7ea; border-radius:8px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#8b909b;">
                                            Identifiant
                                        </div>
                                        <div style="margin-top:4px; font-size:15px; color:#15171c;">
                                            {{ $alumni->email }}
                                        </div>

                                        <div style="margin-top:16px; font-size:12px; text-transform:uppercase; letter-spacing:0.08em; color:#8b909b;">
                                            Mot de passe provisoire
                                        </div>
                                        <div style="margin-top:4px; font-family:'SFMono-Regular',Consolas,'Liberation Mono',monospace; font-size:18px; letter-spacing:0.05em; color:#15171c;">
                                            {{ $password }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 32px 8px 32px;" align="center">
                            <a href="{{ $loginUrl }}"
                               style="display:inline-block; padding:14px 28px; background-color:#16375a; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; border-radius:8px;">
                                Acceder au portail
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 32px 0 32px;">
                            <p style="margin:0; font-size:13px; line-height:20px; color:#5a5f6b;">
                                A votre premiere connexion, il vous sera demande de choisir
                                votre propre mot de passe.
                                @if ($expiresDays !== null)
                                    Ce mot de passe provisoire expire dans {{ $expiresDays }} jours ;
                                    passe ce delai, utilisez « Mot de passe oublie » sur la page de
                                    connexion.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px 28px 32px;">
                            <p style="margin:0; font-size:12px; line-height:18px; color:#8b909b;">
                                Si le bouton ne fonctionne pas, ouvrez cette adresse :
                                <span style="color:#5a5f6b;">{{ $loginUrl }}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
