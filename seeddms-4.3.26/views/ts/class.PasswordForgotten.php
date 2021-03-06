<?php
/**
 * Implementation of PasswordForgotten view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for PasswordForgotten view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_PasswordForgotten extends SeedDMS_Bootstrap_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
function checkForm()
{
	msg = new Array();
	if (document.form1.login.value == "") msg.push("<?php printMLText("js_no_login");?>");
	if (document.form1.email.value == "") msg.push("<?php printMLText("js_no_email");?>");
	if (msg != "") {
  	noty({
  		text: msg.join('<br />'),
  		type: 'error',
      dismissQueue: true,
  		layout: 'topRight',
  		theme: 'defaultTheme',
			_timeout: 1500,
  	});
		return false;
	}
	else
		return true;
}
$(document).ready(function() {
	$('body').on('submit', '#form1', function(ev){
		if(checkForm()) return;
		ev.preventDefault();
	});
});
document.form1.email.focus();
<?php
	} /* }}} */

	function show() { /* {{{ */
		$referrer = $this->params['referrer'];

		$this->htmlStartPage(getMLText("password_forgotten"), "passwordforgotten");
		$this->globalBanner();
		$this->contentStart();
		$this->pageNavigation(getMLText("password_forgotten"));
?>

<?php $this->contentContainerStart(); ?>
<form action="../op/op.PasswordForgotten.php" method="post" id="form1" name="form1">
<?php
		if ($referrer) {
			echo "<input type='hidden' name='referuri' value='".$referrer."'/>";
		}
?>
  <p><?php printMLText("password_forgotten_text"); ?></p>
	<table class="table-condensed">
		<tr>
			<td><?php printMLText("login");?>:</td>
			<td><input type="text" name="login" id="login"></td>
		</tr>
		<tr>
			<td><?php printMLText("email");?>:</td>
			<td><input type="text" name="email" id="email"></td>
		</tr>
		<tr>
			<td></td>
			<td><input class="btn" type="submit" value="<?php printMLText("submit_password_forgotten") ?>"></td>
		</tr>
	</table>
</form>
<?php $this->contentContainerEnd(); ?>
<p><a href="../out/out.Login.php"><?php echo getMLText("login"); ?></a></p>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
