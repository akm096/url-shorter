<?php
http_response_code(404);
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Sayfa Bulunamadı</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .container {
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            border: 1px solid #ddd;
            max-width: 400px;
        }

        h1 {
            font-size: 72px;
            margin: 0;
            color: #333;
        }

        p {
            color: #666;
            margin: 16px 0;
        }

        a {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 20px;
            background: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
        }

        a:hover {
            background: #555;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>404</h1>
        <p>Aradığınız sayfa bulunamadı.</p>
        <a href="/">Ana Sayfaya Dön</a>
    </div>
</body>

</html>