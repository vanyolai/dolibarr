<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
if (!$user->hasRight('completioncertificate','read')) accessforbidden();
$id=GETPOSTINT('id'); $sql="SELECT c.*,s.nom socname,s.address,s.zip,s.town,co.ref orderref FROM ".MAIN_DB_PREFIX."completioncertificate c JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=c.fk_soc JOIN ".MAIN_DB_PREFIX."commande co ON co.rowid=c.fk_commande WHERE c.rowid=".$id." AND c.entity=".(int)$conf->entity;
$r=$db->query($sql); $c=$r?$db->fetch_object($r):null; if(!$c) accessforbidden();
$pdf=pdf_getInstance(); $pdf->SetCreator('Dolibarr'); $pdf->SetTitle($c->ref); $pdf->SetMargins(15,15,15); $pdf->AddPage(); $pdf->SetFont(pdf_getPDFFont($langs),'B',16); $pdf->Cell(0,10,$langs->transnoentities('CompletionCertificate'),0,1,'C'); $pdf->SetFont(pdf_getPDFFont($langs),'',10);
$pdf->Ln(5); $pdf->Cell(45,7,$langs->transnoentities('Ref').':',0,0); $pdf->Cell(0,7,$c->ref,0,1); $pdf->Cell(45,7,$langs->transnoentities('ThirdParty').':',0,0); $pdf->Cell(0,7,$c->socname,0,1); $pdf->Cell(45,7,$langs->transnoentities('Order').':',0,0); $pdf->Cell(0,7,$c->orderref,0,1); $pdf->Cell(45,7,$langs->transnoentities('CompletionDate').':',0,0); $pdf->Cell(0,7,dol_print_date($db->jdate($c->date_completion),'day'),0,1); $pdf->Ln(6);
$pdf->SetFont(pdf_getPDFFont($langs),'B',9); $pdf->Cell(120,7,$langs->transnoentities('Description'),1,0); $pdf->Cell(30,7,$langs->transnoentities('OrderedQty'),1,0,'R'); $pdf->Cell(30,7,$langs->transnoentities('CertifiedQty'),1,1,'R'); $pdf->SetFont(pdf_getPDFFont($langs),'',9);
$r=$db->query("SELECT * FROM ".MAIN_DB_PREFIX."completioncertificate_line WHERE fk_completioncertificate=".$id." ORDER BY rang,rowid"); while($r && ($l=$db->fetch_object($r))){$y=$pdf->GetY(); if($y>260){$pdf->AddPage();} $pdf->MultiCell(120,7,$l->description,1,'L',false,0); $pdf->Cell(30,7,price($l->qty_ordered),1,0,'R'); $pdf->Cell(30,7,price($l->qty_certified),1,1,'R');}
if(!empty($c->note_public)){ $pdf->Ln(6); $pdf->MultiCell(0,6,$c->note_public,0,'L');}
$pdf->Ln(18); $pdf->Cell(80,6,$langs->transnoentities('ContractorSignature'),0,0,'C'); $pdf->Cell(20,6,'',0,0); $pdf->Cell(80,6,$langs->transnoentities('CustomerSignature'),0,1,'C');
$pdf->Output(dol_sanitizeFileName($c->ref).'.pdf','I');
