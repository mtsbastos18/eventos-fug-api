<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado Disponível</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #842626;
            margin: 0;
            padding: 0;
            color: #842626;
        }

        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .header {
            background-color: #842626;
            color: #ffffff;
            text-align: center;
            padding: 20px 0;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            padding: 30px;
        }

        .content h2 {
            color: #ed7130;
            margin-top: 0;
        }

        .event-details {
            background-color: #f9f9f9;
            border-left: 4px solid #ed7130;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .event-details p {
            margin: 8px 0;
            font-size: 15px;
        }

        .cta {
            text-align: center;
            margin: 30px 0;
        }

        .cta a {
            display: inline-block;
            background-color: #ed7130;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
            padding: 14px 32px;
            border-radius: 6px;
            font-size: 16px;
        }

        .notice {
            font-size: 13px;
            color: #777777;
            text-align: center;
        }

        .footer {
            background-color: #f1f1f1;
            text-align: center;
            padding: 20px;
            font-size: 13px;
            color: #777777;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Seu certificado está disponível</h1>
        </div>

        <div class="content">
            <h2>Olá, {{ $certificate->participant->name }}!</h2>
            <p>O certificado de participação do evento <strong>{{ $certificate->event->title }}</strong> já está
                disponível para download.</p>

            <div class="event-details">
                <p><strong>📅 Evento:</strong> {{ $certificate->event->title }}</p>
                <p><strong>📅 Data:</strong> {{ $certificate->event->date->format('d/m/Y') }}</p>
                <p><strong>🔖 Código de autenticidade:</strong> {{ $certificate->code }}</p>
            </div>

            <div class="cta">
                <a href="{{ $certificateUrl }}">Baixar meu certificado</a>
            </div>

            <p class="notice">Para baixar, você precisará informar os últimos dígitos do seu CPF cadastrado na
                inscrição.</p>
        </div>

        <div class="footer">
            <p>Este é um e-mail automático, por favor não responda.</p>
        </div>
    </div>
</body>

</html>
