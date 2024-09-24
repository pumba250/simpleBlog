<?php include 'header.tpl'; 
session_start();
// Генерация двух случайных чисел для капчи
$num1 = rand(1, 10);
$num2 = rand(1, 10);
// Сохраним правильный ответ в сессии
$_SESSION['captcha_answer'] = $num1 + $num2;
?><div class="w3-card-4 w3-margin w3-white">
<div id="contact" class="w3-container w3-center w3-padding-32">
<h2 class="w3-wide">Свяжитесь с нами</h2>
<p class="w3-opacity w3-center"><i>simpleBlog оставьте отзыв о нас!</i></p>
<? echo $errors[0]; ?>
<form method="POST">
<input type="hidden" name="action" value="contact">
<input class="w3-input" type="text" placeholder="Имя" required name="name">
<input class="w3-input" type="text" placeholder="Email" required name="email">
<input class="w3-input" type="text" placeholder="Сообщение" required name="message">
<p>Сколько будет <?php echo $num1; ?> + <?php echo $num2; ?>?</p>
    <input type="text" name="captcha" required placeholder="Введите ответ">
    <br>
<button class="w3-button w3-black" type="submit">ОТПРАВИТЬ</button>
</form>
</div>
</div>
<?php include 'footer.tpl'; ?>