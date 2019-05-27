<? if(!defined("B_PROLOG_INCLUDED")||B_PROLOG_INCLUDED!==true)die();

global $USER;
if (
	!isset($_GET['USER_LOGIN']) ||
	!isset($_GET['USER_CHECKWORD']) ||
	$USER->IsAuthorized()
) {
	header("Location: http://dev.movado.flex.by/404.php");
	die();
}
?>
