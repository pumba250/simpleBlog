<?php include 'header.tpl'; 
session_start();
// Генерация двух случайных чисел для капчи
$num1 = rand(1, 20);
$num2 = rand(1, 10);
// Сохраним правильный ответ в сессии
$_SESSION['captcha_answer'] = $num1 + $num2;
//$expected_answer = $_SESSION['captcha_answer'];

?><div class="w3-card-4 w3-margin w3-white">
<div id="contact" class="w3-container w3-center w3-padding-32">
<?php if (!$user): ?><h2 class="w3-wide">Регистрация</h2>
<p class="w3-opacity w3-center"><? flash(); ?></p>
<form method="POST">
    <input type="hidden" name="action" value="register">
    
    <label for="username">Логин:</label>
    <input type="text" name="username" id="username" placeholder="Логин" required><br>
    
    <label for="password">Пароль:</label>
    <input type="password" name="password" id="password" placeholder="Пароль" required><br>
    
    <label for="email">Email:</label>
    <input type="email" name="email" id="email" placeholder="Email" required><br>
    
    <label for="question"><p>Сколько будет <?php echo $num1; ?> + <?php echo $num2; ?>?</label>
    <input type="text" name="posted_answer" id="question" placeholder="Введите ответ" required><br>
    
    <button type="submit">Зарегистрироваться</button>
</form><?php else: ?><p class="w3-opacity w3-center"><a href="?">На главную</a></p><?php endif; ?></div>
</div>
<?php include 'footer.tpl'; ?>
