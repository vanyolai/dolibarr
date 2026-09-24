<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
dol_include_once('/completioncertificate/class/certificate.class.php');

$langs->loadLangs(array('orders', 'completioncertificate@completioncertificate'));

$id = GETPOSTINT('id');

if (!$user->hasRight('completioncertificate', 'read')) {
	accessforbidden();
}

$result = restrictedArea($user, 'commande', $id, '');
if ($result < 0) {
	accessforbidden();
}

$object = new Commande($db);
if ($object->fetch($id) <= 0) {
	accessforbidden();
}
$object->fetch_thirdparty();

llxHeader('', $langs->trans('CompletionCertificates'));

$form = new Form($db);
$head = commande_prepare_head($object);
print dol_get_fiche_head($head, 'completioncertificates', $langs->trans('CustomerOrder'), -1, $object->picto);

$linkback = '<a href="'.DOL_URL_ROOT.'/commande/list.php">'.$langs->trans('BackToList').'</a>';
$morehtmlref = '<div class="refidno">'.$object->thirdparty->getNomUrl(1).'</div>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="underbanner clearboth"></div>';

if ((int) $object->status > 0 && $user->hasRight('completioncertificate', 'write')) {
	print '<div class="tabsAction">';
	print dolGetButtonAction(
		'',
		$langs->trans('CreateCompletionCertificate'),
		'default',
		dol_buildpath('/completioncertificate/card.php', 1).'?orderid='.((int) $object->id),
		''
	);
	print '</div>';
}

$sql = 'SELECT rowid, ref, date_completion, status, datec';
$sql .= ' FROM '.$db->prefix().'completioncertificate';
$sql .= ' WHERE entity = '.((int) $conf->entity);
$sql .= ' AND fk_commande = '.((int) $object->id);
$sql .= ' ORDER BY date_completion DESC, rowid DESC';

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
} else {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Ref').'</td>';
	print '<td class="center">'.$langs->trans('CompletionDate').'</td>';
	print '<td class="center">'.$langs->trans('DateCreation').'</td>';
	print '<td class="right">'.$langs->trans('Status').'</td>';
	print '</tr>';

	$count = 0;
	while ($row = $db->fetch_object($resql)) {
		$count++;
		$certificate = new Certificate($db);
		if ($certificate->fetch((int) $row->rowid) <= 0) {
			continue;
		}

		print '<tr class="oddeven">';
		print '<td>'.$certificate->getNomUrl(1).'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($row->date_completion), 'day').'</td>';
		print '<td class="center">'.dol_print_date($db->jdate($row->datec), 'dayhour').'</td>';
		print '<td class="right">'.$certificate->getLibStatut(5).'</td>';
		print '</tr>';
	}

	if ($count === 0) {
		print '<tr class="oddeven"><td colspan="4" class="opacitymedium">'.$langs->trans('NoCompletionCertificates').'</td></tr>';
	}

	print '</table>';
	print '</div>';
	$db->free($resql);
}

print dol_get_fiche_end();

llxFooter();
$db->close();
