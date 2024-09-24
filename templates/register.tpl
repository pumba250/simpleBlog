<?php include 'header.tpl'; ?>
        <form method="POST">
            <input type="hidden" name="action" value="register">
            <input type="text" name="username" placeholder="Логин" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit">Зарегистрироваться</button>
        </form>
<?php include 'footer.tpl'; ?>