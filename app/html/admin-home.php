<script>
    var login = async () => {
        var passwordElement = document.getElementById('password');
        var password = passwordElement.value;
        if (password.length === 0) {
            passwordElement.focus();
            return;
        }
        var result = await call('login', {
            password: password
        });
        if (result === 'ok') {
            location.reload();
        } else if (result === 'invalid') {
            alert('The password is not valid!');
        }
    };
</script>
<div class="title" style="max-width:360px;margin:0 auto;">Welcome to the<br>administrator panel</div>
<div style="max-width:260px;margin:0 auto;text-align:center;padding-top:20px;">
    <label class="label" for="password">Password</label>
    <input class="textbox" id="password" type="password" style="margin-top:20px;" />
    <span class="button" style="margin-top:20px;" onclick="login()">Login</span>
</div>
<div class="hint" style="max-width:360px;margin:0 auto;padding-top:40px;">This is the admininistrator panel for <?= $host ?>. Login to manage the profiles and groups on that host and get invitation keys for new ones.</div>