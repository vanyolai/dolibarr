<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

if (empty($conf) || !is_object($conf)) {
	exit(1);
}

global $linkedObjectBlock, $noMoreLinkedObjectBlockAfter, $db;

$langs->load('completioncertificate@completioncertificate');

$ilink = 0;
foreach ($linkedObjectBlock as $key => $objectlink) {
	$ilink++;
	$trclass = 'oddeven';
	if ($ilink == count($linkedObjectBlock) && empty($noMoreLinkedObjectBlockAfter) && count($linkedObjectBlock) <= 1) {
		$trclass .= ' liste_sub_total';
	}
	?>
	<tr class="<?php echo $trclass; ?>">
		<td><?php echo $langs->trans('CompletionCertificate'); ?></td>
		<td><?php echo $objectlink->getNomUrl(1); ?></td>
		<td></td>
		<td class="center"><?php echo dol_print_date($objectlink->date_completion ? $db->jdate($objectlink->date_completion) : 0, 'day'); ?></td>
		<td class="right"></td>
		<td class="right"><?php echo $objectlink->getLibStatut(5); ?></td>
		<td></td>
	</tr>
	<?php
}
