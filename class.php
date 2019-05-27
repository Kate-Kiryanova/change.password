<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Application,
	Bitrix\Main\Localization\Loc;

class FlxMDChangePassword extends CBitrixComponent
{

	private $arRequest = [];

	private $bCheckFields = false;
	private $isRegisterUser = false;
	private $bCheckWord = false;
	private $idUser;
	private $bChangePassword = false;

	private $arResponse = [];

	public function executeComponent()
	{
		Loc::loadMessages(__FILE__);

		$this->arResult["PARAMS_HASH"] = md5(serialize($this->arParams).$this->GetTemplateName());

		$this->arRequest = Application::getInstance()->getContext()->getRequest();

		if (
			$this->arRequest->isAjaxRequest() &&
			$this->arRequest->getPost('FLXMD_AJAX') === 'Y' &&
			$this->arRequest->getPost('PARAMS_HASH') === $this->arResult["PARAMS_HASH"]
		) {
			$this->checkFields();

			if ($this->bCheckFields)
				$this->isRegisterUser();

			if ($this->isRegisterUser)
				$this->checkCheckword();

			if ($this->bCheckWord)
				$this->changePassword();

			if ($this->bChangePassword)
				$this->sendEmail();

			$this->sendResponseAjax();

		} else {

			$this->IncludeComponentTemplate();

		}
	}

	public function checkFields()
	{
		if (
			$this->arRequest->getPost('PARAMS_HASH') === $this->arResult["PARAMS_HASH"] &&
			empty($this->arRequest->getPost('CHECK_EMPTY')) &&
			!empty($this->arRequest->getPost('change-email')) &&
			check_email($this->arRequest->getPost('change-email')) &&
			!empty($this->arRequest->getPost('change-checkword')) &&
			!empty($this->arRequest->getPost('change-password')) &&
			!empty($this->arRequest->getPost('change-password-repeat')) &&
			htmlspecialchars($this->arRequest->getPost('change-password')) == htmlspecialchars($this->arRequest->getPost('change-password-repeat')) &&
			check_bitrix_sessid()
		) {

			$this->bCheckFields = true;

		} else {

			$this->arResponse = ['STATUS' => 'ERROR', 'MESSAGE' => Loc::getMessage("FLXMD_CHANGE_PASSWORD_FIELDS_ERROR")];
			$this->bCheckFields = false;

		}
	}

	public function isRegisterUser()
	{
		$this->arSearchUser = \Bitrix\Main\UserTable::GetList(array(
			'select' => array('ID', 'ACTIVE', 'LOGIN', 'EMAIL',),
			'filter' => array('LOGIN' => htmlspecialchars($this->arRequest->getPost('change-email')))
		));

		if ( $this->arUser = $this->arSearchUser->fetch() ) {
			if ($this->arUser["ACTIVE"] == 'Y') {
				$this->isRegisterUser = true;
			} else {
				$this->arResponse = ['STATUS' => 'ERROR', 'MESSAGE' => Loc::getMessage("FLXMD_CHANGE_PASSWORD_IS_REGISTER_WITHOUT_ACTIVE")];
			}
		} else {
			$this->arResponse = ['STATUS' => 'ERROR', 'MESSAGE' => Loc::getMessage("FLXMD_CHANGE_PASSWORD_IS_NOT_REGISTER")];
		}

	}

	public function checkCheckword()
	{
		global $DB;

		CTimeZone::Disable();
		$db_check = $DB->Query(
			"SELECT ID, LID, EMAIL, LOGIN, CHECKWORD, ".$DB->DateToCharFunction("CHECKWORD_TIME", "FULL")." as CHECKWORD_TIME ".
			"FROM b_user ".
			"WHERE LOGIN='".$DB->ForSql(htmlspecialchars($this->arRequest->getPost('change-email')), 0)."' AND (EXTERNAL_AUTH_ID IS NULL OR EXTERNAL_AUTH_ID='')");
		CTimeZone::Enable();

		if (!($res = $db_check->Fetch()))
			$this->arResponse = [
				'STATUS' => 'ERROR',
				'MESSAGE' => preg_replace(
					"/#LOGIN#/i",
					htmlspecialcharsbx(htmlspecialchars($this->arRequest->getPost('change-email'))),
					Loc::getMessage('FLXMD_CHANGE_PASSWORD_LOGIN_NOT_FOUND')
				)
			];

		$salt = substr($res["CHECKWORD"], 0, 8);
		if (
			$res["CHECKWORD"] == '' ||
			$res["CHECKWORD"] != $salt.md5($salt.htmlspecialchars($this->arRequest->getPost('change-checkword')))
		) {
			$this->arResponse = [
				'STATUS' => 'ERROR',
				'MESSAGE' => preg_replace(
					"/#LOGIN#/i",
					htmlspecialcharsbx(htmlspecialchars($this->arRequest->getPost('change-email'))),
					Loc::getMessage('FLXMD_CHANGE_PASSWORD_INCORRECT_CHECKWORD')
				)
			];
		} else {
			$this->bCheckWord = true;
			$this->idUser = $res['ID'];
		}
	}

	public function changePassword()
	{
		$obUser = new CUser;
		$res = $obUser->Update($this->idUser, array("PASSWORD" => $this->arRequest->getPost('change-password')));
		if(!$res) {
			$this->arResponse = ['STATUS' => 'ERROR', 'MESSAGE' => $obUser->LAST_ERROR];
		} else {
			$this->bChangePassword = true;
		}
	}

	public function sendEmail()
	{

		$arFields = array(
			'EMAIL' => htmlspecialchars($this->arRequest->getPost('change-email')),
			'PASSWORD' => htmlspecialchars($this->arRequest->getPost('change-password'))
		);

		if (CEvent::Send("USER_PASS_CHANGED", SITE_ID, $arFields)) {
			$this->arResponse = ['STATUS' => 'SUCCESS'];
		} else {
			$this->arResponse = ['STATUS' => 'ERROR', 'MESSAGE' => Loc::getMessage("FLXMD_CHANGE_PASSWORD_SEND_MAIL_ERROR")];
		}

	}

	public function sendResponseAjax() {

		global $APPLICATION;

		$APPLICATION->RestartBuffer();

		echo json_encode($this->arResponse);

		die();

	}

}
