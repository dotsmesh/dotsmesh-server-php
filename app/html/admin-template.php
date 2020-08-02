<?php

use BearFramework\App;

$app = App::get();

?><html>

<head>
    <meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,minimal-ui">
    <style>
        html,
        body {
            padding: 0;
            margin: 0;
            min-height: 100%;
        }

        * {
            outline: none;
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
            line-height: 160%;
        }

        h1,
        h2,
        h3 {
            margin: 0;
        }

        body,
        input {
            font-size: 17px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        body {
            background: #111111;
            color: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        body>* {
            max-width: 680px;
            margin: 0 auto;
            padding: 0 15px;
            box-sizing: border-box;
            width: 100%;
        }

        h1 {
            font-size: 22px;
            line-height: 160%;
            font-weight: bold;
            padding-bottom: 20px;
            display: block;
        }

        h2 {
            font-size: 18px;
            line-height: 160%;
            font-weight: bold;
            padding-bottom: 20px;
            padding-top: 40px;
        }

        .logout-button {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background-color: #333;
            background-size: 20px;
            background-position: center;
            background-repeat: no-repeat;
        }

        .logo {
            text-decoration: none;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            padding-left: 1px;
            user-select: none;
            display: inline-block;
        }

        .logo span {
            display: block;
            font-size: 35px;
            margin-top: -10px;
            margin-left: -1px;
        }

        .label-1 {
            width: 100%;
            display: block;
            font-size: 15px;
        }

        .textbox-1 {
            width: 100%;
            height: 46px;
            display: block;
            padding: 0 15px;
            line-height: 42px;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 15px;
            margin-top: 5px;
            background-color: #f5f5f5;
            color: #000;
            border: 0;
        }

        .button-1 {
            display: inline-block;
            color: #fff;
            text-decoration: none;
            padding: 0 20px;
            height: 46px;
            line-height: 44px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.08);
            box-sizing: border-box;
            font-size: 15px;
            cursor: pointer;
        }

        .button-1[disabled] {
            cursor: default;
        }

        .button-1:hover {
            background-color: rgba(255, 255, 255, 0.12);
        }

        .button-1:active {
            background-color: rgba(255, 255, 255, 0.16);
        }

        .card {
            border-radius: 8px;
            background-color: #fff;
            color: #000;
            padding: 10px 55px 10px 55px;
            min-height: 56px;
            box-sizing: border-box;
            position: relative;
            margin-bottom: 10px;
        }

        .card-image {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #333;
            position: absolute;
            margin-left: -45px;
            background-size: 20px;
            background-position: center;
            background-repeat: no-repeat;
        }

        .card-right-button {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            position: absolute;
            right: 10px;
            margin-left: -45px;
            box-sizing: border-box;
            cursor: pointer;
            background-size: 20px;
            background-position: center;
            background-repeat: no-repeat;
        }

        .card-right-button:hover {
            background-color: rgba(0, 0, 0, 0.04);
        }

        .card-right-button:active {
            background-color: rgba(0, 0, 0, 0.08);
        }

        .card-title {
            line-height: 36px;
            word-break: break-word;
        }

        .card-hint {
            padding-top: 3px;
            padding-bottom: 3px;
            font-size: 14px;
            word-break: break-word;
        }

        .icon-key {
            background-image: url('data:image/svg+xml;base64,<?= base64_encode('<svg role="img" xmlns="http://www.w3.org/2000/svg" width="48px" height="48px" viewBox="0 0 24 24" aria-labelledby="keyIconTitle" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" color="#2329D6"> <title id="keyIconTitle">Key</title> <polyline points="21 16 21 12 12 12"/> <circle cx="7" cy="12" r="4"/> <path d="M17,15 L17,12"/> </svg>') ?>');
        }

        .icon-profile {
            background-image: url('data:image/svg+xml;base64,<?= base64_encode('<svg role="img" xmlns="http://www.w3.org/2000/svg" width="48px" height="48px" viewBox="0 0 24 24" aria-labelledby="personIconTitle" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" color="#2329D6"> <title id="personIconTitle">Person</title> <path d="M4,20 C4,17 8,17 10,15 C11,14 8,14 8,9 C8,5.667 9.333,4 12,4 C14.667,4 16,5.667 16,9 C16,14 13,14 14,15 C16,17 20,17 20,20"/> </svg>') ?>');
        }

        .icon-delete {
            background-image: url('data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19.152 4.695h-14m9-1h-4m-4 5v10c0 .67.33 1 1 1h10c.67 0 1-.33 1-1v-10"/></svg>') ?>');
        }

        .icon-logout {
            background-image: url('data:image/svg+xml;base64,<?= base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"><path d="M18 15l3-3-3-3m-6.5 3H20"/><path d="M21 12h-1m-5-8v16H4V4z"/></svg>') ?>');
        }

    </style>

    <script>
        var call = async (action, args = {}) => {
            var response = await fetch(location.href, {
                method: 'POST',
                cache: 'no-cache',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    args: args
                })
            });
            return await response.text();
        };
        var logout = async () => {
            if (confirm('Are you sure you want to log out?')) {
                var result = await call('logout');
                if (result === 'ok') {
                    location.reload();
                }
            }
        }
    </script>
</head>

<body style="padding-bottom:40px;">
    <header style="padding-top:40px;padding-bottom:25px;">
        <div class="logo">DOTS MESH<span><?= strtoupper($host) ?></span></div>

        <?php
        if ($hasLoggedInAdmin) {
            echo '<span class="logout-button icon-logout" onclick="logout();"></a>';
        }
        ?>
    </header>
    <main>

        {{content}}
    </main>

</body>

</html>