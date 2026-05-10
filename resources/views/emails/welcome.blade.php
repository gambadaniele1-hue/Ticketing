<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benvenuto in {{ $tenantName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 500px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
        }

        .button {
            display: inline-block;
            margin-top: 24px;
            padding: 14px 32px;
            background-color: #4F46E5;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
        }

        .footer {
            font-size: 12px;
            color: #999999;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Benvenuto in {{ $tenantName }}! 👋</h2>
        <p>Ciao <strong>{{ $name }}</strong>,</p>
        <p>Il tuo account è stato creato con successo. Clicca sul bottone qui sotto per accedere al tuo workspace.</p>

        <a href="{{ $loginUrl }}" class="button">Accedi al workspace</a>

        <p style="margin-top: 24px; font-size: 14px; color: #666666;">
            Oppure copia questo link nel browser:<br>
            <span style="color: #4F46E5;">{{ $loginUrl }}</span>
        </p>

        <div class="footer">
            Ticketing SaaS — Sistema automatico, non rispondere a questa email.
        </div>
    </div>
</body>

</html>