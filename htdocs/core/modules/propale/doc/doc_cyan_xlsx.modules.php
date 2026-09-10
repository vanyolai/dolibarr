<?php
/* Copyright (C) 2026 Krisztian Vanyolai
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       htdocs/core/modules/propale/doc/doc_cyan_xlsx.modules.php
 * \ingroup    propale
 * \brief      Styled XLSX document generator for commercial proposals
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

/**
 * Styled XLSX proposal generator inspired by the Cyan proposal layout.
 *
 * No external spreadsheet library is required. The XLSX package is written
 * directly as Office Open XML and packed with PHP's ZipArchive extension.
 */
class doc_cyan_xlsx extends ModelePDFPropales
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $name = 'Cyan XLSX';

	/** @var string */
	public $description = 'Formatted Excel export for commercial proposals';

	/** @var int */
	public $update_main_doc_field = 0;

	/** @var string */
	public $type = 'xlsx';

	/** @var string */
	public $version = 'dolibarr';

	/** @var string */
	public $scandir = '';

	/** @var int */
	public $option_logo = 0;
	/** @var int */
	public $option_tva = 1;
	/** @var int */
	public $option_modereg = 0;
	/** @var int */
	public $option_condreg = 0;
	/** @var int */
	public $option_multilang = 1;
	/** @var int */
	public $option_escompte = 0;
	/** @var int */
	public $option_credit_note = 0;
	/** @var int */
	public $option_freetext = 1;
	/** @var int */
	public $option_draft_watermark = 0;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$this->db = $db;
		$this->emetteur = $mysoc;
		$langs->loadLangs(array('main', 'companies', 'products', 'propal', 'bills'));
	}

	/**
	 * Build proposal XLSX on disk.
	 *
	 * @param  Propal     $object              Proposal
	 * @param  ?Translate $outputlangs         Output language
	 * @param  string     $srctemplatepath     Unused
	 * @param  int        $hidedetails         Hide price/tax details
	 * @param  int        $hidedesc            Hide descriptions
	 * @param  int        $hideref             Unused: references are intentionally never exported
	 * @return int                              1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs, $conf;

		if (!class_exists('ZipArchive')) {
			$this->error = 'PHP ZipArchive extension is required to generate XLSX files';
			return 0;
		}

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'companies', 'products', 'propal', 'bills'));

		if (empty($conf->propal->multidir_output[$conf->entity])) {
			$this->error = 'Proposal output directory is not configured';
			return 0;
		}

		$object->fetch_thirdparty();

		if (!empty($object->specimen)) {
			$dir = $conf->propal->multidir_output[$conf->entity];
			$file = $dir.'/SPECIMEN.xlsx';
		} else {
			$objectref = dol_sanitizeFileName($object->ref);
			$entity = isset($object->entity) ? $object->entity : $conf->entity;
			$dir = $conf->propal->multidir_output[$entity].'/'.$objectref;
			$file = $dir.'/'.$objectref.'.xlsx';
		}

		if (!file_exists($dir) && dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return 0;
		}

		$sheetXml = $this->buildSheet($object, $conf, (bool) $hidedetails, (bool) $hidedesc);
		if ($sheetXml === false) {
			return 0;
		}

		$tmpfile = $file.'.tmp';
		@unlink($tmpfile);

		$zip = new ZipArchive();
		$res = $zip->open($tmpfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		if ($res !== true) {
			$this->error = 'Unable to create XLSX archive';
			return 0;
		}

		$zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
		$zip->addFromString('_rels/.rels', $this->rootRelsXml());
		$zip->addFromString('docProps/app.xml', $this->appXml());
		$zip->addFromString('docProps/core.xml', $this->coreXml());
		$zip->addFromString('xl/workbook.xml', $this->workbookXml());
		$zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
		$zip->addFromString('xl/styles.xml', $this->stylesXml());
		$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

		if (!$zip->close()) {
			$this->error = 'Unable to finalize XLSX archive';
			@unlink($tmpfile);
			return 0;
		}

		if (!@rename($tmpfile, $file)) {
			$this->error = 'Unable to move generated XLSX file into proposal directory';
			@unlink($tmpfile);
			return 0;
		}

		if (!empty($conf->global->MAIN_UMASK)) {
			@chmod($file, octdec($conf->global->MAIN_UMASK));
		}

		$this->result = array('fullpath' => $file);
		return 1;
	}

	/**
	 * Build worksheet XML.
	 *
	 * @param Propal $object Proposal
	 * @param Conf $conf Configuration
	 * @param bool $hidedetails Hide prices and taxes
	 * @param bool $hidedesc Hide descriptions
	 * @return string|false
	 */
	private function buildSheet($object, $conf, $hidedetails, $hidedesc)
	{
		$rows = array();
		$merges = array();

		$rows[] = $this->row(1, array(
			$this->s('A1', 'AJÁNLAT / COMMERCIAL PROPOSAL', 1),
		));
		$merges[] = 'A1:G1';

		$meta = array(
			array('Ajánlat száma / Proposal ref', $object->ref),
			array('Dátum / Date', !empty($object->date) ? dol_print_date($object->date, 'day', 'tzuser') : ''),
			array('Érvényes eddig / Valid until', !empty($object->fin_validite) ? dol_print_date($object->fin_validite, 'day', 'tzuser') : ''),
			array('Pénznem / Currency', !empty($object->multicurrency_code) ? $object->multicurrency_code : $conf->currency),
		);
		if (!empty($object->ref_client)) {
			$meta[] = array('Ügyfél hivatkozása / Customer ref', $object->ref_client);
		}

		$r = 3;
		foreach ($meta as $m) {
			$rows[] = $this->row($r, array(
				$this->s('A'.$r, $m[0], 2),
				$this->s('B'.$r, $m[1], 3),
			));
			$merges[] = 'B'.$r.':G'.$r;
			$r++;
		}

		$r++;
		$rows[] = $this->row($r, array($this->s('A'.$r, 'KIÁLLÍTÓ / ISSUER', 4)));
		$merges[] = 'A'.$r.':C'.$r;
		$rows[] = $this->row($r, array($this->s('E'.$r, 'ÜGYFÉL / CUSTOMER', 4)));
		$merges[] = 'E'.$r.':G'.$r;
		$r++;

		$thirdparty = is_object($object->thirdparty) ? $object->thirdparty : null;
		$issuerName = !empty($this->emetteur->name) ? $this->emetteur->name : '';
		$issuerAddress = $this->oneLineAddress($this->emetteur);
		$customerName = $thirdparty ? $thirdparty->name : '';
		$customerAddress = $thirdparty ? $this->oneLineAddress($thirdparty) : '';

		$rows[] = $this->row($r, array(
			$this->s('A'.$r, 'Név / Name', 2),
			$this->s('B'.$r, $issuerName, 3),
			$this->s('E'.$r, 'Név / Name', 2),
			$this->s('F'.$r, $customerName, 3),
		));
		$merges[] = 'B'.$r.':C'.$r;
		$merges[] = 'F'.$r.':G'.$r;
		$r++;

		$rows[] = $this->row($r, array(
			$this->s('A'.$r, 'Cím / Address', 2),
			$this->s('B'.$r, $issuerAddress, 3),
			$this->s('E'.$r, 'Cím / Address', 2),
			$this->s('F'.$r, $customerAddress, 3),
		));
		$merges[] = 'B'.$r.':C'.$r;
		$merges[] = 'F'.$r.':G'.$r;
		$r += 2;

		if (!empty($object->note_public)) {
			$rows[] = $this->row($r, array(
				$this->s('A'.$r, 'Megjegyzés / Public note', 2),
				$this->s('B'.$r, $this->plainText($object->note_public), 3),
			), 30);
			$merges[] = 'B'.$r.':G'.$r;
			$r += 2;
		}

		$tableHeaderRow = $r;
		$headers = array('#', 'Megnevezés / Description', 'Mennyiség / Qty');
		if (!$hidedetails) {
			$headers[] = 'Egységár nettó / Unit price';
			$headers[] = 'Kedv. %';
			$headers[] = 'ÁFA %';
			$headers[] = 'Nettó / Total excl. tax';
		}

		$cells = array();
		foreach ($headers as $idx => $label) {
			$col = $this->colName($idx + 1);
			$cells[] = $this->s($col.$r, $label, 5);
		}
		$rows[] = $this->row($r, $cells, 28);
		$r++;

		$lineNo = 0;
		foreach ((array) $object->lines as $line) {
			$lineNo++;
			$designation = $this->lineDesignation($line, $hidedesc);

			$cells = array(
				$this->n('A'.$r, $lineNo, 6),
				$this->s('B'.$r, $designation, 7),
				$this->n('C'.$r, $line->qty, 8),
			);

			if (!$hidedetails) {
				$cells[] = $this->n('D'.$r, $line->subprice, 9);
				$cells[] = $this->n('E'.$r, $line->remise_percent, 10);
				$cells[] = $this->n('F'.$r, $line->tva_tx, 10);
				$cells[] = $this->n('G'.$r, $line->total_ht, 9);
			}

			$height = mb_strlen($designation) > 90 ? 38 : (mb_strlen($designation) > 45 ? 28 : 22);
			$rows[] = $this->row($r, $cells, $height);
			$r++;
		}

		$totalStart = $r + 1;
		if (!$hidedetails) {
			$rows[] = $this->row($totalStart, array(
				$this->s('E'.$totalStart, 'Összesen nettó / Total excl. tax', 11),
				$this->n('G'.$totalStart, $object->total_ht, 12),
			));
			$rows[] = $this->row($totalStart + 1, array(
				$this->s('E'.($totalStart + 1), 'ÁFA összesen / VAT total', 11),
				$this->n('G'.($totalStart + 1), $object->total_tva, 12),
			));
			$rows[] = $this->row($totalStart + 2, array(
				$this->s('E'.($totalStart + 2), 'Mindösszesen bruttó / Total incl. tax', 13),
				$this->n('G'.($totalStart + 2), $object->total_ttc, 14),
			));
		}

		$lastCol = $hidedetails ? 'C' : 'G';

		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
		$xml .= '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
		$xml .= '<pane ySplit="'.((int) $tableHeaderRow).'" topLeftCell="A'.($tableHeaderRow + 1).'" activePane="bottomLeft" state="frozen"/>';
		$xml .= '</sheetView></sheetViews>';
		$xml .= '<sheetFormatPr defaultRowHeight="18"/>';
		$xml .= '<cols>';
		$xml .= '<col min="1" max="1" width="5" customWidth="1"/>';
		$xml .= '<col min="2" max="2" width="62" customWidth="1"/>';
		$xml .= '<col min="3" max="3" width="14" customWidth="1"/>';
		if (!$hidedetails) {
			$xml .= '<col min="4" max="4" width="18" customWidth="1"/>';
			$xml .= '<col min="5" max="6" width="11" customWidth="1"/>';
			$xml .= '<col min="7" max="7" width="18" customWidth="1"/>';
		}
		$xml .= '</cols>';
		$xml .= '<sheetData>'.implode('', $rows).'</sheetData>';

		if ($merges) {
			$xml .= '<mergeCells count="'.count($merges).'">';
			foreach ($merges as $merge) {
				$xml .= '<mergeCell ref="'.$merge.'"/>';
			}
			$xml .= '</mergeCells>';
		}

		$xml .= '<autoFilter ref="A'.$tableHeaderRow.':'.$lastCol.max($tableHeaderRow, $r - 1).'"/>';
		$xml .= '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>';
		$xml .= '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>';
		$xml .= '</worksheet>';

		return $xml;
	}

	private function row($r, $cells, $height = null)
	{
		$attrs = ' r="'.((int) $r).'"';
		if ($height !== null) {
			$attrs .= ' ht="'.((int) $height).'" customHeight="1"';
		}
		return '<row'.$attrs.'>'.implode('', $cells).'</row>';
	}

	private function s($ref, $value, $style = 0)
	{
		$value = $this->xml((string) $value);
		return '<c r="'.$ref.'" t="inlineStr" s="'.((int) $style).'"><is><t xml:space="preserve">'.$value.'</t></is></c>';
	}

	private function n($ref, $value, $style = 0)
	{
		$num = is_numeric($value) ? (float) $value : 0;
		$normalized = rtrim(rtrim(sprintf('%.10F', $num), '0'), '.');
		if ($normalized === '' || $normalized === '-0') {
			$normalized = '0';
		}
		return '<c r="'.$ref.'" s="'.((int) $style).'"><v>'.$normalized.'</v></c>';
	}

	private function lineDesignation($line, $hideDesc)
	{
		$parts = array();

		if (!empty($line->product_label)) {
			$parts[] = $this->plainText($line->product_label);
		}
		if (!empty($line->label)) {
			$parts[] = $this->plainText($line->label);
		}
		if (!$hideDesc && !empty($line->desc)) {
			$parts[] = $this->plainText($line->desc);
		}
		if (!$parts && !empty($line->product_desc) && !$hideDesc) {
			$parts[] = $this->plainText($line->product_desc);
		}

		$unique = array();
		foreach ($parts as $part) {
			if ($part !== '' && !in_array($part, $unique, true)) {
				$unique[] = $part;
			}
		}

		return implode("\n", $unique);
	}

	private function plainText($value)
	{
		$text = (string) $value;
		$text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
		$text = preg_replace('/<\/\s*(p|div|li)\s*>/i', "\n", $text);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace("/[ \t]+/u", ' ', $text);
		$text = preg_replace("/\n{3,}/u", "\n\n", $text);
		return trim($text);
	}

	private function oneLineAddress($company)
	{
		if (!is_object($company)) {
			return '';
		}

		$parts = array();
		foreach (array('address', 'zip', 'town', 'state') as $field) {
			if (!empty($company->$field) && !is_object($company->$field)) {
				$parts[] = $company->$field;
			}
		}

		if (!empty($company->country) && is_object($company->country) && !empty($company->country->label)) {
			$parts[] = $company->country->label;
		} elseif (!empty($company->country) && !is_object($company->country)) {
			$parts[] = $company->country;
		}

		return $this->plainText(implode(', ', array_unique($parts)));
	}

	private function colName($index)
	{
		$name = '';
		while ($index > 0) {
			$index--;
			$name = chr(65 + ($index % 26)).$name;
			$index = (int) floor($index / 26);
		}
		return $name;
	}

	private function xml($value)
	{
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	private function contentTypesXml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			.'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			.'<Default Extension="xml" ContentType="application/xml"/>'
			.'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			.'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			.'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			.'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			.'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			.'</Types>';
	}

	private function rootRelsXml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			.'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			.'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			.'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			.'</Relationships>';
	}

	private function workbookXml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			.'<bookViews><workbookView/></bookViews>'
			.'<sheets><sheet name="Ajánlat" sheetId="1" r:id="rId1"/></sheets>'
			.'</workbook>';
	}

	private function workbookRelsXml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			.'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			.'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			.'</Relationships>';
	}

	private function coreXml()
	{
		$now = gmdate('Y-m-d\TH:i:s\Z');
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
			.'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
			.'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			.'<dc:creator>Dolibarr</dc:creator>'
			.'<cp:lastModifiedBy>Dolibarr</cp:lastModifiedBy>'
			.'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created>'
			.'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
			.'</cp:coreProperties>';
	}

	private function appXml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
			.'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
			.'<Application>Dolibarr</Application>'
			.'</Properties>';
	}

	private function stylesXml()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			.'<numFmts count="3">'
			.'<numFmt numFmtId="164" formatCode="#,##0.######"/>'
			.'<numFmt numFmtId="165" formatCode="#,##0.00"/>'
			.'<numFmt numFmtId="166" formatCode="0.##"/>'
			.'</numFmts>'
			.'<fonts count="5">'
			.'<font><sz val="10"/><name val="Aptos"/></font>'
			.'<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font>'
			.'<font><b/><sz val="10"/><color rgb="FF1F2937"/><name val="Aptos"/></font>'
			.'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font>'
			.'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font>'
			.'</fonts>'
			.'<fills count="6">'
			.'<fill><patternFill patternType="none"/></fill>'
			.'<fill><patternFill patternType="gray125"/></fill>'
			.'<fill><patternFill patternType="solid"><fgColor rgb="FF008C95"/><bgColor indexed="64"/></patternFill></fill>'
			.'<fill><patternFill patternType="solid"><fgColor rgb="FFE6F5F6"/><bgColor indexed="64"/></patternFill></fill>'
			.'<fill><patternFill patternType="solid"><fgColor rgb="FF374151"/><bgColor indexed="64"/></patternFill></fill>'
			.'<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
			.'</fills>'
			.'<borders count="3">'
			.'<border><left/><right/><top/><bottom/><diagonal/></border>'
			.'<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>'
			.'<border><left/><right/><top style="medium"><color rgb="FF0F766E"/></top><bottom/><diagonal/></border>'
			.'</borders>'
			.'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			.'<cellXfs count="15">'
			.'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
			.'<xf numFmtId="0" fontId="3" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
			.'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
			.'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="2" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'<xf numFmtId="165" fontId="2" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'<xf numFmtId="0" fontId="4" fillId="5" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'<xf numFmtId="165" fontId="4" fillId="5" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
			.'</cellXfs>'
			.'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			.'</styleSheet>';
	}
}
