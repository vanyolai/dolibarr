<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

// Let Dolibarr's main.inc.php perform the native CSRF validation.
define('CSRFCHECK_WITH_TOKEN', 1);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/completioncertificate/class/certificate.class.php');

$langs->loadLangs(array('orders', 'completioncertificate@completioncertificate'));

if (!$user->hasRight('completioncertificate', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$orderId = GETPOSTINT('orderid');
$action = GETPOST('action', 'aZ09');

$permissiontoadd = $user->hasRight('completioncertificate', 'write');
$permissiontodelete = $user->hasRight('completioncertificate', 'delete');

$certificate = new Certificate($db);
$object = $certificate;

if ($id > 0 && $action !== 'save') {
	$result = $certificate->fetch($id);
	if ($result <= 0) {
		accessforbidden();
	}
}

/*
 * Actions
 */
if ($action === 'save') {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	$orderId = GETPOSTINT('orderid');
	$order = new Commande($db);
	if ($order->fetch($orderId) <= 0 || (int) $order->status <= 0) {
		accessforbidden();
	}

	$requestedQty = array();
	foreach ($order->lines as $line) {
		$rawQty = GETPOST('qty_'.((int) $line->id), 'alphanohtml');
		$requestedQty[(int) $line->id] = (float) price2num($rawQty);
	}

	$dateCompletion = GETPOST('date_completion', 'alpha');
	if ($dateCompletion === '') {
		$dateCompletion = dol_print_date(dol_now(), '%Y-%m-%d');
	}
	$notePublic = GETPOST('note_public', 'restricthtml');

	$newId = $certificate->createFromOrder($order, $user, $dateCompletion, $notePublic, $requestedQty);
	if ($newId > 0) {
		if (!empty($certificate->warnings)) {
			setEventMessages('', $certificate->warnings, 'warnings');
		}
		header('Location: '.dol_buildpath('/completioncertificate/card.php', 1).'?id='.$newId);
		exit;
	}

	setEventMessages($certificate->error, $certificate->errors, 'errors');
	$action = 'create';
}

if ($action === 'validate' && $id > 0) {
	if (!$permissiontoadd) {
		accessforbidden();
	}

	if ($certificate->validate($user) > 0) {
		// Generate the first standard PDF immediately after validation.
		$certificate->fetch($id);
		$certificate->fetch_thirdparty();
		$generationResult = $certificate->generateDocument('standard_certificate', $langs);
		if ($generationResult <= 0) {
			setEventMessages($certificate->error, $certificate->errors, 'warnings');
		}

		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

if ($action === 'delete' && $id > 0) {
	if (!$permissiontodelete) {
		accessforbidden();
	}

	$sourceOrderId = (int) $certificate->fk_commande;
	if ($certificate->deleteDraft() > 0) {
		header('Location: '.DOL_URL_ROOT.'/commande/card.php?id='.$sourceOrderId);
		exit;
	}
	setEventMessages($certificate->error, $certificate->errors, 'errors');
}

/*
 * Native Dolibarr document actions.
 */
if ($id > 0 && ($action === 'builddoc' || $action === 'remove_file')) {
	$objref = dol_sanitizeFileName($certificate->ref);
	$baseOutput = getMultidirOutput($certificate, $certificate->module);
	if (empty($baseOutput)) {
		$baseOutput = DOL_DATA_ROOT.'/completioncertificate';
	}
	$upload_dir = $baseOutput.'/'.$certificate->element.'/'.$objref;

	include DOL_DOCUMENT_ROOT.'/core/actions_builddoc.inc.php';
}

/*
 * View
 */
llxHeader('', $langs->trans('CompletionCertificate'));
print load_fiche_titre($langs->trans('CompletionCertificate'), '', 'check-circle');

if ($orderId > 0 && $id <= 0) {
	$order = new Commande($db);
	if ($order->fetch($orderId) <= 0 || (int) $order->status <= 0) {
		accessforbidden();
	}
	$order->fetch_thirdparty();

	$usedQuantities = $certificate->getUsedQuantitiesForOrder($orderId);

	print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="orderid" value="'.((int) $order->id).'">';

	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Order').'</td><td>'.$order->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$order->thirdparty->getNomUrl(1).'</td></tr>';
	print '<tr><td>'.$langs->trans('CompletionDate').'</td><td>';
	print '<input type="date" name="date_completion" value="'.dol_print_date(dol_now(), '%Y-%m-%d').'">';
	print '</td></tr>';
	print '</table>';
	print '<br>';

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="right">'.$langs->trans('OrderedQty').'</td>';
	print '<td class="right">'.$langs->trans('AlreadyCertifiedQty').'</td>';
	print '<td class="right">'.$langs->trans('RemainingQty').'</td>';
	print '<td class="right">'.$langs->trans('CertifiedQty').'</td>';
	print '</tr>';

	foreach ($order->lines as $line) {
		$lineId = (int) $line->id;
		$orderedQty = (float) $line->qty;
		$usedQty = (float) ($usedQuantities[$lineId] ?? 0.0);
		$remainingQty = max(0.0, $orderedQty - $usedQty);
		$description = Certificate::buildOrderLineDescription($line);

		print '<tr>';
		print '<td>'.dol_htmlentitiesbr($description).'</td>';
		print '<td class="right">'.price($orderedQty).'</td>';
		print '<td class="right">'.price($usedQty).'</td>';
		print '<td class="right">'.price($remainingQty).'</td>';
		print '<td class="right">';
		print '<input class="width75 right" type="number" step="any" min="0" max="'.price2num($remainingQty).'"';
		print ' name="qty_'.$lineId.'" value="'.price2num($remainingQty).'"'.($remainingQty <= 0 ? ' disabled' : '').'>';
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';
	print '<br>';

	print '<label for="note_public">'.$langs->trans('NotePublic').'</label><br>';
	print '<textarea id="note_public" class="quatrevingtpercent" rows="4" name="note_public">'.dol_escape_htmltag(GETPOST('note_public', 'restricthtml')).'</textarea>';

	print '<div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans('Create').'">';
	print ' &nbsp; ';
	print '<a class="button button-cancel" href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $order->id).'">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	print '</form>';
} elseif ($id > 0) {
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($certificate->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.dol_escape_htmltag($certificate->thirdparty_name).'</td></tr>';
	print '<tr><td>'.$langs->trans('Order').'</td><td><a href="'.DOL_URL_ROOT.'/commande/card.php?id='.((int) $certificate->fk_commande).'">'.dol_escape_htmltag($certificate->order_ref).'</a></td></tr>';
	print '<tr><td>'.$langs->trans('CompletionDate').'</td><td>'.dol_print_date($db->jdate($certificate->date_completion), 'day').'</td></tr>';
	print '<tr><td>'.$langs->trans('Status').'</td><td>'.$certificate->getLibStatut(5).'</td></tr>';
	print '</table>';
	print '<br>';

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="right">'.$langs->trans('OrderedQty').'</td>';
	print '<td class="right">'.$langs->trans('CertifiedQty').'</td>';
	print '</tr>';
	foreach ($certificate->lines as $line) {
		print '<tr>';
		print '<td>'.dol_htmlentitiesbr($line->description).'</td>';
		print '<td class="right">'.price($line->qty_ordered).'</td>';
		print '<td class="right">'.price($line->qty_certified).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';

	if ($certificate->note_public !== '') {
		print '<br><div class="opacitymedium">'.$langs->trans('NotePublic').'</div>';
		print '<div class="wordbreak">'.dol_htmlentitiesbr(strip_tags($certificate->note_public)).'</div>';
	}

	print '<div class="tabsAction">';
	if ($certificate->status === Certificate::STATUS_DRAFT && $permissiontoadd) {
		print dolGetButtonAction('', $langs->trans('Validate'), 'default', $_SERVER['PHP_SELF'].'?id='.$id.'&action=validate&token='.newToken(), '');
	}
	if ($certificate->status === Certificate::STATUS_DRAFT && $permissiontodelete) {
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$id.'&action=delete&token='.newToken(), '');
	}
	print '</div>';

	if ($certificate->status === Certificate::STATUS_VALIDATED) {
		$formfile = new FormFile($db);
		$objref = dol_sanitizeFileName($certificate->ref);
		$baseOutput = getMultidirOutput($certificate, $certificate->module);
		if (empty($baseOutput)) {
			$baseOutput = DOL_DATA_ROOT.'/completioncertificate';
		}
		$filedir = $baseOutput.'/'.$certificate->element.'/'.$objref;
		$urlsource = $_SERVER['PHP_SELF'].'?id='.$certificate->id;

		print '<br>';
		print $formfile->showdocuments(
			'completioncertificate:Certificate',
			$certificate->element.'/'.$objref,
			$filedir,
			$urlsource,
			1,
			(int) $permissiontoadd,
			$certificate->model_pdf,
			1,
			0,
			0,
			0,
			0,
			'',
			'',
			'',
			$langs->defaultlang,
			'',
			$certificate
		);
	}
}

llxFooter();
$db->close();
