<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com>
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
	 * Add completion certificate creation to the native order "Create" dropdown.
	 *
	 * @param array<string,mixed> $parameters Hook parameters
	 * @param Commande $object Current customer order
	 * @param string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $arrayforbutaction;

		if (!in_array('ordercard', explode(':', (string) ($parameters['context'] ?? '')), true)) {
			return 0;
		}
		if (!is_object($object) || empty($object->id)) {
			return 0;
		}

		$arrayforbutaction[] = array(
			'lang' => 'completioncertificate@completioncertificate',
			'enabled' => ((int) $object->status > 0),
			'perm' => $user->hasRight('completioncertificate', 'write'),
			'label' => 'CreateCompletionCertificate',
			'url' => '/completioncertificate/card.php?orderid='.((int) $object->id),
		);

		return 0;
	}
}
