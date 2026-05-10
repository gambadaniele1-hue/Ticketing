<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il tuo codice OTP</title>
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

        .code {
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 12px;
            color: #4F46E5;
            margin: 30px 0;
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
        <h2>Il tuo codice di accesso</h2>
        <p>Usa questo codice per completare la registrazione:</p>

        <div class="code">{{ $otp }}</div>

        <p>Il codice scade tra <strong>10 minuti</strong>.</p>
        <p>Se non hai richiesto questo codice, ignora questa email.</p>

        <div class="footer">
            Ticketing SaaS — Sistema automatico, non rispondere a questa email.
        </div>
    </div>
</body>

</html>