<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com>
 *
 * Compatibility endpoint kept for bookmarks created by early 0.x versions.
 * PDF generation is now handled by the native Dolibarr document model.
 */

require '../../main.inc.php';
dol_include_once('/completioncertificate/class/certificate.class.php');

$langs->load('completioncertificate@completioncertificate');

if (!$user->hasRight('completioncertificate', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$certificate = new Certificate($db);
if ($certificate->fetch($id) <= 0) {
	accessforbidden();
}

header('Location: '.dol_buildpath('/completioncertificate/card.php', 1).'?id='.$certificate->id.'#builddoc');
exit;
