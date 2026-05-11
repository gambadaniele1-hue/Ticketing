<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova richiesta di accesso</title>
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

        .user-card {
            background-color: #f8f7ff;
            border: 1px solid #e0deff;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
            text-align: left;
        }

        .user-card p {
            margin: 8px 0;
            font-size: 15px;
            color: #333333;
        }

        .user-card span {
            font-weight: bold;
            color: #4F46E5;
        }

        .button {
            display: inline-block;
            margin-top: 24px;
            padding: 14px 32px;
            background-color: #4F46E5;
            color: #ffffff !important;
            /* ← aggiungi !important */
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
        <h2>🔔 Nuova richiesta di accesso</h2>
        <p>Ciao <strong>{{ $adminName }}</strong>,</p>
        <p>Un nuovo utente vuole accedere al workspace <strong>{{ $tenantName }}</strong> ed è in attesa di
            approvazione.</p>

        <div class="user-card">
            <p>👤 Nome: <span>{{ $newUserName }}</span></p>
            <p>📧 Email: <span>{{ $newUserEmail }}</span></p>
            <p>📅 Richiesta il: <span>{{ $requestedAt }}</span></p>
        </div>

        <p>Accedi al pannello di controllo per accettare o rifiutare la richiesta.</p>

        <a href="{{ $loginUrl }}" class="button">Vai al pannello</a>

        <div class="footer">
            Ticketing SaaS — Sistema automatico, non rispondere a questa email.
        </div>
    </div>
</body>

</html>