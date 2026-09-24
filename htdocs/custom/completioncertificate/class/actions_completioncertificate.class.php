<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

class ActionsCompletionCertificate extends CommonHookActions
{
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (($parameters['currentcontext'] ?? '') !== 'ordercard' || empty($object->id) || $object->status <= 0) {
			return 0;
		}
		if (!$user->hasRight('completioncertificate', 'write')) {
			return 0;
		}

		$langs->load('completioncertificate@completioncertificate');

		$url = dol_buildpath('/completioncertificate/card.php', 1).'?action=create&orderid='.(int) $object->id;
		$label = $langs->trans('CreateCompletionCertificate');
		$createLabel = $langs->trans('Create');

		$urlJson = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$labelJson = json_encode($label, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$createLabelJson = json_encode($createLabel, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		// Dolibarr 23 builds the order "Create" dropdown after this hook runs and
		// does not expose its option array to hooks. Identify that dropdown by
		// its core create links instead of by translated button text.
		print '<script nonce="'.getNonce().'">
		jQuery(function($) {
			var targetUrl = '.$urlJson.';
			var targetLabel = '.$labelJson.';

			function injectCompletionCertificateItem() {
				$(".tabsAction .dropdown-holder .dropdown-content").each(function() {
					var content = $(this);
					if (content.find("[data-completioncertificate-create]").length) {
						return;
					}

					var isOrderCreateMenu = content.find(
						'a[href*="/fourn/commande/card.php"],' +
						'a[href*="/contrat/card.php"],' +
						'a[href*="/expedition/shipment.php"],' +
						'a[href*="/compta/facture/card.php"]'
					).length > 0;

					if (!isOrderCreateMenu) {
						return;
					}

					$("<a>", {
						"class": "dropdown-item",
						"href": targetUrl,
						"text": targetLabel,
						"data-completioncertificate-create": "1"
					}).appendTo(content);
				});
			}

			injectCompletionCertificateItem();
			window.setTimeout(injectCompletionCertificateItem, 0);
			window.setTimeout(injectCompletionCertificateItem, 100);
		});
		</script>';

		return 0;
	}
}
