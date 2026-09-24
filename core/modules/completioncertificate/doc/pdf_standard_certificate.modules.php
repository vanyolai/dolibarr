<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

dol_include_once('/completioncertificate/core/modules/completioncertificate/modules_certificate.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

/**
 * Standard Dolibarr-style PDF model for completion certificates.
 */
class pdf_standard_certificate extends ModelePDFCertificate
{
	public $db;
	public $name = 'standard_certificate';
	public $description;
	public $type = 'pdf';
	public $update_main_doc_field = 0;
	public $phpmin = array(8, 1);
	public $version = 'dolibarr';
	public $emetteur;

	public $page_largeur;
	public $page_hauteur;
	public $format;
	public $marge_gauche;
	public $marge_droite;
	public $marge_haute;
	public $marge_basse;

	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$langs->loadLangs(array('main', 'companies', 'completioncertificate@completioncertificate'));
		$this->description = $langs->trans('DocumentModelStandardPDF');

		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->emetteur = $mysoc;
	}

	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'companies', 'orders', 'completioncertificate@completioncertificate'));

		if ($object->fetch_thirdparty() <= 0) {
			$this->error = $langs->trans('ErrorFailedToLoadThirdParty');
			return -1;
		}

		$dirOutput = getMultidirOutput($object, $object->module);
		if (empty($dirOutput)) {
			$dirOutput = DOL_DATA_ROOT.'/completioncertificate';
		}
		$dirOutput .= '/'.$object->element;

		$objectref = dol_sanitizeFileName($object->ref);
		$dir = $dirOutput.'/'.$objectref;
		$file = $dir.'/'.$objectref.'.pdf';

		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return -1;
		}

		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetCreator('Dolibarr');
		$pdf->SetAuthor($this->emetteur->name);
		$pdf->SetTitle($object->ref);
		$pdf->SetSubject($outputlangs->transnoentities('CompletionCertificate'));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(true, $this->marge_basse + 18);

		if (getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$object->entity])) {
				$logodir = $conf->mycompany->multidir_output[$object->entity];
			}
			$background = $logodir.'/'.getDolGlobalString('MAIN_ADD_PDF_BACKGROUND');
			if (is_readable($background)) {
				$pdf->setSourceFile($background);
				$tplidx = $pdf->importPage(1);
			}
		}

		$pdf->AddPage();
		if (!empty($tplidx)) {
			$pdf->useTemplate($tplidx);
		}

		$tableY = $this->_pagehead($pdf, $object, $outputlangs);
		$pdf->SetY($tableY);
		$this->_tablehead($pdf, $outputlangs);

		$fontSize = pdf_getPDFFontSize($outputlangs) - 1;
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $fontSize);

		foreach ($object->lines as $line) {
			$description = trim((string) $line->description);
			$descHeight = max(7.0, (float) $pdf->getStringHeight(130, $description));
			$rowHeight = max(7.0, $descHeight);

			if ($pdf->GetY() + $rowHeight > ($this->page_hauteur - $this->marge_basse - 28)) {
				$this->_pagefoot($pdf, $object, $outputlangs, 1);
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pdf->SetY($this->marge_haute + 5);
				$this->_tablehead($pdf, $outputlangs);
				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $fontSize);
			}

			$x = $this->marge_gauche;
			$y = $pdf->GetY();

			$pdf->MultiCell(130, $rowHeight, $outputlangs->convToOutputCharset($description), 1, 'L', false, 0, $x, $y);
			$pdf->MultiCell(25, $rowHeight, price($line->qty_ordered), 1, 'R', false, 0, $x + 130, $y);
			$pdf->MultiCell(25, $rowHeight, price($line->qty_certified), 1, 'R', false, 1, $x + 155, $y);
			$pdf->SetY($y + $rowHeight);
		}

		if (!empty($object->note_public)) {
			$pdf->Ln(5);
			$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $fontSize);
			$pdf->MultiCell(0, 5, $outputlangs->transnoentities('NotePublic').':', 0, 'L');
			$pdf->SetFont(pdf_getPDFFont($outputlangs), '', $fontSize);
			$pdf->MultiCell(0, 5, trim(strip_tags($object->note_public)), 0, 'L');
		}

		if ($pdf->GetY() > ($this->page_hauteur - 65)) {
			$this->_pagefoot($pdf, $object, $outputlangs, 1);
			$pdf->AddPage();
			if (!empty($tplidx)) {
				$pdf->useTemplate($tplidx);
			}
			$pdf->SetY($this->marge_haute + 15);
		}

		$pdf->Ln(16);
		$signatureWidth = 70;
		$gap = 30;
		$x = ($this->page_largeur - ($signatureWidth * 2 + $gap)) / 2;
		$y = $pdf->GetY();

		$pdf->Line($x, $y + 14, $x + $signatureWidth, $y + 14);
		$pdf->Line($x + $signatureWidth + $gap, $y + 14, $x + ($signatureWidth * 2) + $gap, $y + 14);
		$pdf->SetXY($x, $y + 15);
		$pdf->Cell($signatureWidth, 5, $outputlangs->transnoentities('ContractorSignature'), 0, 0, 'C');
		$pdf->SetXY($x + $signatureWidth + $gap, $y + 15);
		$pdf->Cell($signatureWidth, 5, $outputlangs->transnoentities('CustomerSignature'), 0, 1, 'C');

		$this->_pagefoot($pdf, $object, $outputlangs, 0);

		$pdf->Close();
		$pdf->Output($file, 'F');

		$this->result = array('fullpath' => $file);
		return 1;
	}

	protected function _pagehead(&$pdf, $object, $outputlangs)
	{
		global $conf;

		$font = pdf_getPDFFont($outputlangs);
		$defaultFontSize = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		$posy = $this->marge_haute;
		$titleWidth = 105;
		$titleX = $this->page_largeur - $this->marge_droite - $titleWidth;

		if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
			if (!empty($this->emetteur->logo)) {
				$logodir = $conf->mycompany->dir_output;
				if (!empty($conf->mycompany->multidir_output[$object->entity])) {
					$logodir = $conf->mycompany->multidir_output[$object->entity];
				}
				$logo = !getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')
					? $logodir.'/logos/thumbs/'.$this->emetteur->logo_small
					: $logodir.'/logos/'.$this->emetteur->logo;
				if (is_readable($logo)) {
					$pdf->Image($logo, $this->marge_gauche, $posy, 0, pdf_getHeightForLogo($logo));
				}
			} else {
				$pdf->SetFont($font, 'B', $defaultFontSize + 1);
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->MultiCell(80, 5, $this->emetteur->name, 0, 'L');
			}
		}

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont($font, 'B', $defaultFontSize + 4);
		$pdf->SetXY($titleX, $posy);
		$pdf->MultiCell($titleWidth, 6, $outputlangs->transnoentities('CompletionCertificate'), 0, 'R');

		$pdf->SetFont($font, 'B', $defaultFontSize);
		$pdf->SetXY($titleX, $posy + 8);
		$pdf->MultiCell($titleWidth, 5, $outputlangs->transnoentities('Ref').' : '.$object->ref, 0, 'R');

		$pdf->SetFont($font, '', $defaultFontSize - 1);
		$pdf->SetXY($titleX, $posy + 14);
		$pdf->MultiCell($titleWidth, 4, $outputlangs->transnoentities('Order').' : '.$object->order_ref, 0, 'R');
		$pdf->SetXY($titleX, $posy + 19);
		$pdf->MultiCell($titleWidth, 4, $outputlangs->transnoentities('CompletionDate').' : '.dol_print_date($this->db->jdate($object->date_completion), 'day', false, $outputlangs, true), 0, 'R');

		$boxY = max(42, $posy + 30);
		$boxWidth = 82;
		$boxHeight = 34;
		$senderX = $this->marge_gauche;
		$recipientX = $this->page_largeur - $this->marge_droite - $boxWidth;

		$senderAddress = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);
		$recipientAddress = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'target', $object);

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont($font, '', $defaultFontSize - 2);
		$pdf->SetXY($senderX + 2, $boxY - 5);
		$pdf->Cell($boxWidth - 4, 4, $outputlangs->transnoentities('ContractorSignature'), 0, 0, 'L');
		$pdf->SetXY($recipientX + 2, $boxY - 5);
		$pdf->Cell($boxWidth - 4, 4, $outputlangs->transnoentities('CustomerSignature'), 0, 0, 'L');

		$pdf->SetFillColor(245, 245, 245);
		$pdf->Rect($senderX, $boxY, $boxWidth, $boxHeight, 'DF');
		$pdf->Rect($recipientX, $boxY, $boxWidth, $boxHeight);

		$pdf->SetFont($font, 'B', $defaultFontSize);
		$pdf->SetXY($senderX + 2, $boxY + 2);
		$pdf->MultiCell($boxWidth - 4, 4, $this->emetteur->name, 0, 'L');
		$pdf->SetFont($font, '', $defaultFontSize - 1);
		$pdf->SetXY($senderX + 2, $pdf->GetY());
		$pdf->MultiCell($boxWidth - 4, 3.5, $senderAddress, 0, 'L');

		$pdf->SetFont($font, 'B', $defaultFontSize);
		$pdf->SetXY($recipientX + 2, $boxY + 2);
		$pdf->MultiCell($boxWidth - 4, 4, pdfBuildThirdpartyName($object->thirdparty, $outputlangs), 0, 'L');
		$pdf->SetFont($font, '', $defaultFontSize - 1);
		$pdf->SetXY($recipientX + 2, $pdf->GetY());
		$pdf->MultiCell($boxWidth - 4, 3.5, $recipientAddress, 0, 'L');

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont($font, '', $defaultFontSize - 1);
		$statementY = $boxY + $boxHeight + 7;
		$pdf->SetXY($this->marge_gauche, $statementY);
		$pdf->MultiCell(
			$this->page_largeur - $this->marge_gauche - $this->marge_droite,
			5,
			$outputlangs->transnoentities('CompletionCertificateStatement'),
			0,
			'L'
		);

		return $pdf->GetY() + 4;
	}

	protected function _tablehead(&$pdf, $outputlangs)
	{
		$fontSize = pdf_getPDFFontSize($outputlangs) - 1;
		$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', $fontSize);
		$pdf->SetFillColor(235, 235, 235);

		$x = $this->marge_gauche;
		$pdf->SetX($x);
		$pdf->Cell(130, 7, $outputlangs->transnoentities('Description'), 1, 0, 'L', true);
		$pdf->Cell(25, 7, $outputlangs->transnoentities('OrderedQty'), 1, 0, 'R', true);
		$pdf->Cell(25, 7, $outputlangs->transnoentities('CertifiedQty'), 1, 1, 'R', true);
	}

	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS') ? 1 : 0;
		return pdf_pagefoot(
			$pdf,
			$outputlangs,
			'COMPLETIONCERTIFICATE_FREE_TEXT',
			$this->emetteur,
			$this->marge_basse,
			$this->marge_gauche,
			$this->page_hauteur,
			$object,
			$showdetails,
			$hidefreetext
		);
	}
}
