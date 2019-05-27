# change.password
1C-Bitrix component with form for change password

# Код вызова компонента:

$APPLICATION->IncludeComponent(
	"flxmd:change.password",
	"change_password",
	array(
		"COMPONENT_TEMPLATE" => "change_password",
	),
	false
);
