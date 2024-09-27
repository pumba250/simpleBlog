</div>
<!-- Introduction menu -->
<div class="w3-col l4">
  <!-- About Card -->
  <div class="w3-card w3-margin w3-margin-top">
  <?php if ($user): ?><img src="<?= htmlspecialchars($user['avatar']);?>" style="width:120px"><?php endif; ?>
    <div class="w3-container w3-white">
      <?php if ($user): ?>
	  <p><form class="mt-5" method="post" action="/admin/do_logout.php"></p>
        <p>Привет, <?= htmlspecialchars($user['username']) ?>!<button type="submit" class="btn btn-primary">Выйти</button></form></p>
		<?php if ($user['isadmin']=='9'): ?><p><a href="/admin/">Admin Panel</a></p><?php endif; ?>
    <?php else: ?>
<p><button onclick="document.getElementById('id01').style.display='block'" class="w3-button w3-gray w3-large">Login</button>

  <div id="id01" class="w3-modal">
    <div class="w3-modal-content w3-card-4 w3-animate-zoom" style="max-width:600px">
  
      <div class="w3-center"><br>
        <span onclick="document.getElementById('id01').style.display='none'" class="w3-button w3-xlarge w3-transparent w3-display-topright" title="Close Modal">×</span>
        <img src="/images/avatar_g.png" alt="Avatar" style="width:30%" class="w3-circle w3-margin-top">
      </div>
      <form method="POST" class="w3-container">
        <div class="w3-section">
		<input type="hidden" name="action" value="login">
          <label><b>Username</b></label>
          <input class="w3-input w3-border w3-margin-bottom" type="text" placeholder="Enter Username" name="username" required>
          <label><b>Password</b></label>
          <input class="w3-input w3-border" type="password" placeholder="Enter Password" name="password" required>
          <button class="w3-button w3-block w3-gray w3-section w3-padding" type="submit">Login</button>
          <input class="w3-check w3-margin-top" type="checkbox" checked="checked"> Remember me
        </div>
      </form>

      <div class="w3-container w3-border-top w3-padding-16 w3-light-grey">
        <button onclick="document.getElementById('id01').style.display='none'" type="button" class="w3-button w3-red">Cancel</button>
        <!--<span class="w3-right w3-padding w3-hide-small">Forgot <a href="#">password?</a></span>-->
      </div>

    </div>
  </div></p>
	<p><a href="?action=register">Регистрация</a></p>
    <?php endif; ?>
	<p><a href="?">Главная</a></p>
	<p><a href="?action=contact">Обратная связь</a></p>
    </div>
  </div><hr>
  <!-- Posts -->
  <div class="w3-card w3-margin">
    <div class="w3-container w3-padding">
      <h4>Последние 3 поста</h4>
    </div>
    <ul class="w3-ul w3-hoverable w3-white">
<?php if ($lastThreeNews): ?>
    <?php foreach ($lastThreeNews as $newsItem): ?>
      <li class="w3-padding-16">
        <span class="w3-large"><?= htmlspecialchars($newsItem['title']) ?></span><br>
        <span><?= $newsItem['created_at'] ?></span>
      </li>
	<?php endforeach; ?>
    <?php else: ?>
        <p>Нет новостей</p>
    <?php endif; ?>
    </ul>
  </div>
  <hr>
  <!-- Labels / tags -->
  <div class="w3-card w3-margin">
        <div class="w3-container w3-padding">
            <h4>Теги</h4>
        </div>
        <div class="w3-container w3-white">
            <p>
                <?php if ($allTags): ?>
                    <?php foreach ($allTags as $tag): ?>
                        <span class="w3-tag w3-light-grey w3-small w3-margin-bottom"><a class="w3-button" href="?tags=<?= htmlspecialchars($tag['name']) ?>"><?= htmlspecialchars($tag['name']) ?></a></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="w3-tag w3-light-grey w3-small w3-margin-bottom">Нет тегов</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
<!-- END Introduction Menu -->
</div>
<!-- END GRID -->
</div><br>
<!-- END w3-content -->
</div>
<!-- Footer -->
<footer class="w3-container w3-dark-grey w3-padding-32 w3-margin-top">
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?= $currentPage - 1 ?>" class="w3-button w3-black w3-padding-large w3-margin-bottom">Пред.</a>
            <?php else: ?>
                <button class="w3-button w3-black w3-disabled w3-padding-large w3-margin-bottom">Пред.</button>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1 ?>" class="w3-button w3-black w3-padding-large w3-margin-bottom">След. &raquo;</a>
            <?php else: ?>
                <button class="w3-button w3-black w3-disabled w3-padding-large w3-margin-bottom">След. &raquo;</button>
            <?php endif; ?>
        </div>
  <p>Design by <a href="https://www.w3schools.com/w3css/default.asp" target="_blank">w3.css</a> Copyright &copy; <?php echo date("Y");?> by <b><?php echo $_SERVER['SERVER_NAME'];?></b>Coded by <b><a href="https://github.com/pumba250/simpleBlog/">pumba250</a></b></p>
</footer>
</body></html>
