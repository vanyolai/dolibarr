<?php
/* Copyright (C) 2026 Krisztian Vanyolai
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

class ActionsCompletionCertificate extends CommonHookActions
{
	/** @var DoliDB */
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add "Create completion certificate" to the order Create dropdown.
	 *
	 * Dolibarr 23 calls addMoreActionsButtons before it builds the core Create
	 * dropdown, so the dropdown option array cannot be extended directly here.
	 * We therefore inject one menu entry on DOM ready. The JavaScript itself is
	 * kept in a nowdoc to avoid PHP/JavaScript quoting collisions.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param Commande $object Current order
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (($parameters['currentcontext'] ?? '') !== 'ordercard') {
			return 0;
		}
		if (!is_object($object) || empty($object->id) || (int) $object->status <= 0) {
			return 0;
		}
		if (!$user->hasRight('completioncertificate', 'write')) {
			return 0;
		}

		$langs->load('completioncertificate@completioncertificate');

		$url = dol_buildpath('/completioncertificate/card.php', 1).'?action=create&orderid='.(int) $object->id;
		$label = $langs->trans('CreateCompletionCertificate');

		$urlJson = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$labelJson = json_encode($label, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if ($urlJson === false || $labelJson === false) {
			return 0;
		}

		$javascript = <<<'JS'
jQuery(function ($) {
	var targetUrl = __TARGET_URL__;
	var targetLabel = __TARGET_LABEL__;

	$(".tabsAction .dropdown-holder .dropdown-content").each(function () {
		var content = $(this);

		if (content.find("[data-completioncertificate-create]").length) {
			return;
		}

		var isOrderCreateMenu = content.find(
			'a[href*="/fourn/commande/card.php?action=create"],' +
			'a[href*="/contrat/card.php?action=create"],' +
			'a[href*="/expedition/shipment.php"],' +
			'a[href*="/compta/facture/card.php?action=create"]'
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
});
JS;

		$javascript = str_replace(
			array('__TARGET_URL__', '__TARGET_LABEL__'),
			array($urlJson, $labelJson),
			$javascript
		);

		print '<script nonce="'.getNonce().'">'.$javascript.'</script>';

		return 0;
	}
}
