<!DOCTYPE html>
<html lang="tk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .wrapper {
            background-color: #0f172a;
            padding: 40px 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #1e293b;
            border-radius: 12px;
            border: 1px solid #334155;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .header {
            background-color: #0f172a;
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #334155;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: #38bdf8;
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .logo span {
            color: #f43f5e;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 20px;
            font-weight: 700;
            color: #f8fafc;
            margin-top: 0;
            margin-bottom: 16px;
        }

        .text {
            font-size: 16px;
            line-height: 26px;
            color: #94a3b8;
            margin-top: 0;
            margin-bottom: 30px;
        }

        .action-area {
            text-align: center;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-block;
            background-color: #38bdf8;
            color: #0f172a !important;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(56, 189, 248, 0.2);
        }

        .footer {
            background-color: #111827;
            padding: 24px 30px;
            text-align: center;
            border-top: 1px solid #334155;
        }

        .footer-text {
            font-size: 13px;
            line-height: 20px;
            color: #64748b;
            margin: 0;
        }

        @media only screen and (max-width: 600px) {
            .content {
                padding: 30px 20px;
            }

            .wrapper {
                padding: 20px 10px;
            }
        }
    </style>
</head>

<body>
    <table role="presentation" class="wrapper">
        <tr>
            <td align="center">
                <table role="presentation" class="container">
                    <tr>
                        <td class="header">
                            <a href="#" class="logo">Programmers<span>Arena</span></a>
                        </td>
                    </tr>

                    <tr>
                        <td class="content">
                            <h1 class="greeting">{{ $greeting }}</h1>
                            <p class="text">{{ $line1 }}</p>

                            <div class="action-area">
                                <a href="{{ $url }}" class="btn" target="_blank">{{ $actionText }}</a>
                            </div>

                            <p class="text" style="margin-bottom: 0;">{{ $line2 }}</p>
                        </td>
                    </tr>

                    <tr>
                        <td class="footer">
                            <p class="footer-text">
                                &copy; {{ date('Y') }} ProgrammersArena. Ähli hukuklar goralan.<br>
                                Eger soraglaryňyz bolsa, goldaw toparymyz bilen habarlaşyp bilersiňiz.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
