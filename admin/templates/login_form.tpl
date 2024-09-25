<!DOCTYPE html>
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title><? echo $pageTitle; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link id="theme-style" rel="stylesheet" href="/css/w3.css">
<link id="theme-style" rel="stylesheet" href="/css/css">
<style>
body,h1,h2,h3,h4,h5 {font-family: "Raleway", sans-serif}
</style>
</head>
<body class="w3-light-grey">
<div class="w3-content" style="max-width:1400px">
<header class="w3-container w3-center w3-padding-32"> 
  <h1>Вход в административную панель</h1>
</header>
<div class="w3-row w3-center">
<div class="w3-col">
        <div class="w3-card-4 w3-margin w3-white">
        <div class="w3-container">
        </div>
        <div class="w3-container">
            <p>    <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="text" name="username" placeholder="Логин" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <button type="submit">Войти</button>
        <?php if (!empty($error)): ?>
            <p style="color:red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
    </form></p>
        </div>
    </div>
    <hr>
</div>
</div><br>
</div>
<!-- Footer -->
<footer class="w3-container w3-dark-grey w3-padding-32 w3-margin-top w3-center">
  <p>Powered by <a href="https://www.w3schools.com/w3css/default.asp" target="_blank">w3.css</a> Copyright &copy; <?php echo date("Y");?> by <b><?php echo $_SERVER['SERVER_NAME'];?></b></p>
</footer>
</body></html>
