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
<div style="max-width:300px;padding-top:100px;padding-bottom:100px;">
    <label class="label-1" for="password">Password</label>
    <input class="textbox-1" id="password" type="password">
    <span class="button-1" style="margin-top:20px;" onclick="login()">Login</span>
</div>
<div style="font-size:13px;color:#999;max-width:400px;">This is the admin panel for <?= $host ?>. Login to manage the profiles and groups on that host and get invitation keys for new ones.</div>