<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Dolibarr business object for a completion certificate.
 */
class Certificate extends CommonObject
{
	public const STATUS_DRAFT = 0;
	public const STATUS_VALIDATED = 1;
	public const STATUS_CANCELED = 9;

	public $module = 'completioncertificate';
	public $element = 'certificate';
	public $table_element = 'completioncertificate';
	public $picto = 'check-circle';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;
	public $TRIGGER_PREFIX = 'COMPLETIONCERTIFICATE_CERTIFICATE';

	public $rowid;
	public $entity = 1;
	public $ref = '';
	public $socid = 0;
	public $fk_soc = 0;
	public $fk_commande = 0;
	public $date_completion = '';
	public $note_public = '';
	public $status = self::STATUS_DRAFT;
	public $fk_user_author = 0;
	public $fk_user_valid = 0;
	public $date_creation = 0;
	public $order_ref = '';
	public $thirdparty_name = '';
	public $model_pdf = 'standard_certificate';
	public $lines = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Load certificate header and lines.
	 *
	 * @param int $id Certificate ID
	 * @param string|null $ref Certificate reference
	 * @return int 1 if found, 0 if not found, negative on error
	 */
	public function fetch($id, $ref = null)
	{
		global $conf;

		$sql = 'SELECT c.*, s.nom AS thirdparty_name, co.ref AS order_ref';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate AS c';
		$sql .= ' INNER JOIN '.$this->db->prefix().'societe AS s ON s.rowid = c.fk_soc';
		$sql .= ' INNER JOIN '.$this->db->prefix().'commande AS co ON co.rowid = c.fk_commande';
		$sql .= ' WHERE c.entity = '.((int) $conf->entity);
		if ($id > 0) {
			$sql .= ' AND c.rowid = '.((int) $id);
		} elseif ($ref !== null && $ref !== '') {
			$sql .= " AND c.ref = '".$this->db->escape($ref)."'";
		} else {
			return 0;
		}

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
		$this->rowid = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->ref = (string) $obj->ref;
		$this->fk_soc = (int) $obj->fk_soc;
		$this->socid = (int) $obj->fk_soc;
		$this->fk_commande = (int) $obj->fk_commande;
		$this->date_completion = (string) $obj->date_completion;
		$this->note_public = (string) ($obj->note_public ?? '');
		$this->status = (int) $obj->status;
		$this->fk_user_author = (int) ($obj->fk_user_author ?? 0);
		$this->fk_user_valid = (int) ($obj->fk_user_valid ?? 0);
		$this->date_creation = !empty($obj->datec) ? $this->db->jdate($obj->datec) : 0;
		$this->order_ref = (string) $obj->order_ref;
		$this->thirdparty_name = (string) $obj->thirdparty_name;

		return $this->fetchLines();
	}

	public function fetchLines()
	{
		$this->lines = array();

		$sql = 'SELECT * FROM '.$this->db->prefix().'completioncertificate_line';
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

	/**
	 * Return the number of completion certificates attached to an order.
	 * Used by Dolibarr's native dynamic tab badge mechanism.
	 *
	 * @param int $orderId Customer order ID
	 * @param mixed $unused Compatibility argument supplied by complete_head_from_modules()
	 * @return int
	 */
	public function getOrderCertificateCount($orderId, $unused = null)
	{
		global $conf;

		$sql = 'SELECT COUNT(*) AS nb';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND fk_commande = '.((int) $orderId);

		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return (int) ($obj->nb ?? 0);
	}

	public function getUsedQuantitiesForOrder($orderId)
	{
		global $conf;

		$result = array();
		$sql = 'SELECT l.fk_commandedet, SUM(l.qty_certified) AS qty_used';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate_line AS l';
		$sql .= ' INNER JOIN '.$this->db->prefix().'completioncertificate AS c ON c.rowid = l.fk_completioncertificate';
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

			$linesToCreate[] = array('line' => $line, 'qty' => $qty);
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

		$sql = 'INSERT INTO '.$this->db->prefix().'completioncertificate (';
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
		$sql .= "'".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -4;
		}

		$newId = (int) $this->db->last_insert_id($this->db->prefix().'completioncertificate');

		foreach ($linesToCreate as $item) {
			$line = $item['line'];
			$qty = (float) $item['qty'];
			$description = self::buildOrderLineDescription($line);

			$sql = 'INSERT INTO '.$this->db->prefix().'completioncertificate_line (';
			$sql .= 'fk_completioncertificate, fk_commandedet, fk_product, description, qty_ordered, qty_certified, rang';
			$sql .= ') VALUES (';
			$sql .= $newId.',';
			$sql .= ((int) $line->id).',';
			$sql .= (!empty($line->fk_product) ? (int) $line->fk_product : 'NULL').',';
			$sql .= "'".$this->db->escape($description)."',";
			$sql .= ((float) $line->qty).',';
			$sql .= $qty.',';
			$sql .= ((int) $line->rang).')';

			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -5;
			}
		}

		$this->db->commit();

		if ($this->fetch($newId) <= 0) {
			return -6;
		}

		// Native Dolibarr object relation: customer order -> completion certificate.
		$linkResult = $this->add_object_linked('commande', (int) $order->id, $user);
		if ($linkResult <= 0) {
			$this->warnings[] = $langs->trans('CompletionCertificateLinkWarning');
		}

		return $newId;
	}

	public function validate($user)
	{
		global $conf, $langs;

		if ($this->id <= 0 || $this->status !== self::STATUS_DRAFT) {
			$this->error = $langs->trans('CompletionCertificateNotDraft');
			return -1;
		}

		$sql = 'UPDATE '.$this->db->prefix().'completioncertificate';
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
		global $conf, $langs, $user;

		if ($this->id <= 0 || $this->status !== self::STATUS_DRAFT) {
			$this->error = $langs->trans('CompletionCertificateDeleteDraftOnly');
			return -1;
		}

		// Remove native Dolibarr object links first.
		$this->deleteObjectLinked(null, '', null, '', 0, $user);

		$this->db->begin();
		if (!$this->db->query('DELETE FROM '.$this->db->prefix().'completioncertificate_line WHERE fk_completioncertificate = '.((int) $this->id))) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$sql = 'DELETE FROM '.$this->db->prefix().'completioncertificate';
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

	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '')
	{
		global $langs;

		$label = dol_escape_htmltag($this->ref);
		$url = dol_buildpath('/completioncertificate/card.php', 1).'?id='.((int) $this->id);
		$picto = $withpicto ? img_picto($langs->trans('CompletionCertificate'), $this->picto, 'class="pictofixedwidth"').' ' : '';

		return '<a class="'.dol_escape_htmltag($morecss).'" href="'.$url.'">'.$picto.$label.'</a>';
	}

	public function getLibStatut($mode = 0)
	{
		global $langs;

		if ($this->status === self::STATUS_VALIDATED) {
			return $langs->trans('Validated');
		}
		if ($this->status === self::STATUS_CANCELED) {
			return $langs->trans('Canceled');
		}
		return $langs->trans('Draft');
	}

	/**
	 * Keep the selected document model in memory.
	 * This object currently has a single registered model.
	 */
	public function setDocModel($user, $modelpdf)
	{
		$this->model_pdf = $modelpdf;
		return 1;
	}

	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		if (empty($modele)) {
			$modele = 'standard_certificate';
		}

		return $this->commonGenerateDocument(
			'core/modules/completioncertificate/doc/',
			$modele,
			$outputlangs,
			$hidedetails,
			$hidedesc,
			$hideref,
			$moreparams
		);
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
			$main .= "\n".$description;
		}

		return $main;
	}

	private static function plainText($value)
	{
		$value = (string) $value;
		$value = preg_replace('/<br\s*\/?>/i', "\n", $value);
		$value = preg_replace('/<\/p>/i', "\n", $value);
		$value = strip_tags($value);
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = str_replace("\r", '', $value);
		return trim($value);
	}

	private function getNextReference()
	{
		global $conf;

		$prefix = 'TI-'.date('Y').'-';
		$sql = 'SELECT MAX(CAST(SUBSTRING(ref, 9) AS UNSIGNED)) AS maxnum';
		$sql .= ' FROM '.$this->db->prefix().'completioncertificate';
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
