<?php
/* Copyright (C) 2026 Krisztian Vanyolai
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Business object for customer-order completion certificates.
 */
class CompletionCertificate
{
	public const STATUS_DRAFT = 0;
	public const STATUS_VALIDATED = 1;
	public const STATUS_CANCELED = 9;

	/** @var DoliDB */
	private $db;

	/** @var int */
	public $id = 0;
	/** @var int */
	public $entity = 1;
	/** @var string */
	public $ref = '';
	/** @var int */
	public $fk_soc = 0;
	/** @var int */
	public $fk_commande = 0;
	/** @var string */
	public $date_completion = '';
	/** @var string */
	public $note_public = '';
	/** @var int */
	public $status = self::STATUS_DRAFT;
	/** @var int */
	public $fk_user_author = 0;
	/** @var int */
	public $fk_user_valid = 0;
	/** @var string */
	public $order_ref = '';
	/** @var string */
	public $thirdparty_name = '';
	/** @var array<int,object> */
	public $lines = array();
	/** @var string */
	public $error = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function fetch($id)
	{
		global $conf;

		$sql = 'SELECT c.*, s.nom AS thirdparty_name, co.ref AS order_ref';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'completioncertificate AS c';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = c.fk_soc';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'commande AS co ON co.rowid = c.fk_commande';
		$sql .= ' WHERE c.rowid = '.((int) $id);
		$sql .= ' AND c.entity = '.((int) $conf->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return 0;
		}

		$this->id = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->ref = (string) $obj->ref;
		$this->fk_soc = (int) $obj->fk_soc;
		$this->fk_commande = (int) $obj->fk_commande;
		$this->date_completion = (string) $obj->date_completion;
		$this->note_public = (string) ($obj->note_public ?? '');
		$this->status = (int) $obj->status;
		$this->fk_user_author = (int) ($obj->fk_user_author ?? 0);
		$this->fk_user_valid = (int) ($obj->fk_user_valid ?? 0);
		$this->order_ref = (string) $obj->order_ref;
		$this->thirdparty_name = (string) $obj->thirdparty_name;

		return $this->fetchLines();
	}

	public function fetchLines()
	{
		$this->lines = array();

		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'completioncertificate_line';
		$sql .= ' WHERE fk_completioncertificate = '.((int) $this->id);
		$sql .= ' ORDER BY rang, rowid';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$this->lines[] = $obj;
		}
		$this->db->free($resql);

		return 1;
	}

	public function getUsedQuantitiesForOrder($orderId)
	{
		global $conf;

		$result = array();
		$sql = 'SELECT l.fk_commandedet, SUM(l.qty_certified) AS qty_used';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'completioncertificate_line AS l';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'completioncertificate AS c ON c.rowid = l.fk_completioncertificate';
		$sql .= ' WHERE c.entity = '.((int) $conf->entity);
		$sql .= ' AND c.fk_commande = '.((int) $orderId);
		$sql .= ' AND c.status IN ('.self::STATUS_DRAFT.', '.self::STATUS_VALIDATED.')';
		$sql .= ' GROUP BY l.fk_commandedet';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return $result;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$result[(int) $obj->fk_commandedet] = (float) $obj->qty_used;
		}
		$this->db->free($resql);

		return $result;
	}

	public function createFromOrder($order, $user, $dateCompletion, $notePublic, array $requestedQty)
	{
		global $conf, $langs;

		if (empty($order->id) || empty($order->socid)) {
			$this->error = $langs->trans('CompletionCertificateInvalidSourceOrder');
			return -1;
		}
		if ((int) $order->status <= 0) {
			$this->error = $langs->trans('CompletionCertificateOrderMustBeValidated');
			return -1;
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCompletion)) {
			$this->error = $langs->trans('CompletionCertificateInvalidDate');
			return -1;
		}

		$order->getLinesArray();
		$used = $this->getUsedQuantitiesForOrder((int) $order->id);

		$linesToCreate = array();
		foreach ($order->lines as $line) {
			$lineId = (int) $line->id;
			$orderedQty = (float) $line->qty;
			$usedQty = (float) ($used[$lineId] ?? 0.0);
			$availableQty = max(0.0, $orderedQty - $usedQty);
			$qty = (float) ($requestedQty[$lineId] ?? 0.0);

			if ($qty < 0) {
				$qty = 0.0;
			}
			if ($qty > $availableQty) {
				$qty = $availableQty;
			}
			if ($qty <= 0) {
				continue;
			}

			$linesToCreate[] = array(
				'line' => $line,
				'qty' => $qty,
			);
		}

		if (empty($linesToCreate)) {
			$this->error = $langs->trans('CompletionCertificateNoQuantity');
			return -2;
		}

		$this->db->begin();
		$ref = $this->getNextReference();
		if ($ref === '') {
			$this->db->rollback();
			return -3;
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'completioncertificate (';
		$sql .= 'entity, ref, fk_soc, fk_commande, date_completion, note_public, status, fk_user_author, datec';
		$sql .= ') VALUES (';
		$sql .= ((int) $conf->entity).',';
		$sql .= "'".$this->db->escape($ref)."',";
		$sql .= ((int) $order->socid).',';
		$sql .= ((int) $order->id).',';
		$sql .= "'".$this->db->escape($dateCompletion)."',";
		$sql .= "'".$this->db->escape($notePublic)."',";
		$sql .= self::STATUS_DRAFT.',';
		$sql .= ((int) $user->id).',';
		$sql .= "'".$this->db->idate(dol_now())."'";
		$sql .= ')';

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -4;
		}

		$newId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'completioncertificate');
		foreach ($linesToCreate as $item) {
			$line = $item['line'];
			$qty = (float) $item['qty'];
			$description = self::buildOrderLineDescription($line);

			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'completioncertificate_line (';
			$sql .= 'fk_completioncertificate, fk_commandedet, fk_product, description, qty_ordered, qty_certified, rang';
			$sql .= ') VALUES (';
			$sql .= $newId.',';
			$sql .= ((int) $line->id).',';
			$sql .= (!empty($line->fk_product) ? (int) $line->fk_product : 'NULL').',';
			$sql .= "'".$this->db->escape($description)."',";
			$sql .= ((float) $line->qty).',';
			$sql .= $qty.',';
			$sql .= ((int) $line->rang);
			$sql .= ')';

			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -5;
			}
		}

		$this->db->commit();
		return $newId;
	}

	public function validate($user)
	{
		global $conf, $langs;

		if ($this->id <= 0 || $this->status !== self::STATUS_DRAFT) {
			$this->error = $langs->trans('CompletionCertificateNotDraft');
			return -1;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'completioncertificate';
		$sql .= ' SET status = '.self::STATUS_VALIDATED;
		$sql .= ', fk_user_valid = '.((int) $user->id);
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND status = '.self::STATUS_DRAFT;

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$this->status = self::STATUS_VALIDATED;
		$this->fk_user_valid = (int) $user->id;
		return 1;
	}

	public function deleteDraft()
	{
		global $conf, $langs;

		if ($this->id <= 0 || $this->status !== self::STATUS_DRAFT) {
			$this->error = $langs->trans('CompletionCertificateDeleteDraftOnly');
			return -1;
		}

		$this->db->begin();
		if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'completioncertificate_line WHERE fk_completioncertificate = '.((int) $this->id))) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'completioncertificate';
		$sql .= ' WHERE rowid = '.((int) $this->id);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' AND status = '.self::STATUS_DRAFT;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	public static function buildOrderLineDescription($line)
	{
		$ref = trim((string) ($line->product_ref ?? $line->ref ?? ''));
		$customLabel = trim((string) ($line->label ?? ''));
		$productLabel = trim((string) ($line->product_label ?? $line->libelle ?? ''));
		$label = $customLabel !== '' ? $customLabel : $productLabel;
		$description = self::plainText($line->desc ?? $line->description ?? '');

		$main = $ref;
		if ($label !== '') {
			$main .= ($main !== '' ? ' - ' : '').$label;
		}

		if ($main === '') {
			return $description;
		}
		if ($description !== '' && $description !== $label && $description !== $main) {
			$main .= "
".$description;
		}

		return $main;
	}

	private static function plainText($value)
	{
		$value = (string) $value;
		$value = preg_replace('/<br\s*\/?>/i', "
", $value);
		$value = preg_replace('/<\/p>/i', "
", $value);
		$value = strip_tags($value);
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = str_replace("", '', $value);
		return trim($value);
	}

	private function getNextReference()
	{
		global $conf;

		$prefix = 'TI-'.date('Y').'-';
		$sql = 'SELECT MAX(CAST(SUBSTRING(ref, 9) AS UNSIGNED)) AS maxnum';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= " AND ref LIKE '".$this->db->escape($prefix)."%'";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return '';
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		$next = ((int) ($obj->maxnum ?? 0)) + 1;

		return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
	}
}
