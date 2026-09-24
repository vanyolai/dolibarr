<?php
/* Copyright (C) 2026 Krisztian Vanyolai */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
dol_include_once('/completioncertificate/class/completioncertificate.class.php');

$langs->loadLangs(array('orders', 'completioncertificate@completioncertificate'));

if (!$user->hasRight('completioncertificate', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$certificate = new CompletionCertificate($db);
if ($certificate->fetch($id) <= 0) {
	accessforbidden();
}
if ($certificate->status !== CompletionCertificate::STATUS_VALIDATED) {
	accessforbidden();
}

$pdf = pdf_getInstance();
$pdf->SetCreator('Dolibarr');
$pdf->SetTitle($certificate->ref);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

$font = pdf_getPDFFont($langs);
$pdf->SetFont($font, 'B', 16);
$pdf->Cell(0, 10, $langs->transnoentities('CompletionCertificate'), 0, 1, 'C');
$pdf->SetFont($font, '', 10);
$pdf->Ln(5);

$pdf->Cell(45, 7, $langs->transnoentities('Ref').':', 0, 0);
$pdf->Cell(0, 7, $certificate->ref, 0, 1);
$pdf->Cell(45, 7, $langs->transnoentities('ThirdParty').':', 0, 0);
$pdf->Cell(0, 7, $certificate->thirdparty_name, 0, 1);
$pdf->Cell(45, 7, $langs->transnoentities('Order').':', 0, 0);
$pdf->Cell(0, 7, $certificate->order_ref, 0, 1);
$pdf->Cell(45, 7, $langs->transnoentities('CompletionDate').':', 0, 0);
$pdf->Cell(0, 7, dol_print_date($db->jdate($certificate->date_completion), 'day'), 0, 1);
$pdf->Ln(6);

$printTableHeader = static function ($pdf, $font, $langs) {
	$pdf->SetFont($font, 'B', 9);
	$pdf->Cell(120, 7, $langs->transnoentities('Description'), 1, 0);
	$pdf->Cell(30, 7, $langs->transnoentities('OrderedQty'), 1, 0, 'R');
	$pdf->Cell(30, 7, $langs->transnoentities('CertifiedQty'), 1, 1, 'R');
	$pdf->SetFont($font, '', 9);
};

$printTableHeader($pdf, $font, $langs);

foreach ($certificate->lines as $line) {
	$description = (string) $line->description;
	$descriptionHeight = max(7.0, (float) $pdf->getStringHeight(120, $description));
	$rowHeight = max(7.0, $descriptionHeight);

	if ($pdf->GetY() + $rowHeight > 270) {
		$pdf->AddPage();
		$printTableHeader($pdf, $font, $langs);
	}

	$x = $pdf->GetX();
	$y = $pdf->GetY();

	$pdf->MultiCell(120, $rowHeight, $description, 1, 'L', false, 0, $x, $y);
	$pdf->MultiCell(30, $rowHeight, price($line->qty_ordered), 1, 'R', false, 0, $x + 120, $y);
	$pdf->MultiCell(30, $rowHeight, price($line->qty_certified), 1, 'R', false, 1, $x + 150, $y);
	$pdf->SetY($y + $rowHeight);
}

if ($certificate->note_public !== '') {
	$pdf->Ln(6);
	$pdf->SetFont($font, 'B', 9);
	$pdf->Cell(0, 6, $langs->transnoentities('NotePublic').':', 0, 1);
	$pdf->SetFont($font, '', 9);
	$pdf->MultiCell(0, 6, trim(strip_tags($certificate->note_public)), 0, 'L');
}

if ($pdf->GetY() > 240) {
	$pdf->AddPage();
}
$pdf->Ln(18);
$pdf->Cell(80, 6, $langs->transnoentities('ContractorSignature'), 0, 0, 'C');
$pdf->Cell(20, 6, '', 0, 0);
$pdf->Cell(80, 6, $langs->transnoentities('CustomerSignature'), 0, 1, 'C');

$pdf->Output(dol_sanitizeFileName($certificate->ref).'.pdf', 'I');
