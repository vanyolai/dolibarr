<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
$langs->loadLangs(array('orders','completioncertificate@completioncertificate'));
if (!$user->hasRight('completioncertificate','read')) accessforbidden();
$id=GETPOSTINT('id'); $orderid=GETPOSTINT('orderid'); $action=GETPOST('action','aZ09');
function cc_ref($db){ global $conf; $prefix='TI-'.date('Y').'-'; $sql="SELECT MAX(CAST(SUBSTRING(ref,9) AS UNSIGNED)) n FROM ".MAIN_DB_PREFIX."completioncertificate WHERE entity=".(int)$conf->entity." AND ref LIKE '".$db->escape($prefix)."%'"; $r=$db->query($sql); $n=1; if($r && ($o=$db->fetch_object($r))) $n=((int)$o->n)+1; return $prefix.str_pad((string)$n,4,'0',STR_PAD_LEFT); }
function cc_plain_line_text($value)
{
 $value = (string) $value;
 $value = preg_replace('/<br\\s*\\/?>/i', "\n", $value);
 $value = preg_replace('/<\\/p>/i', "\n", $value);
 $value = strip_tags($value);
 $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
 $value = str_replace("\r", '', $value);
 return trim($value);
}
function cc_line_description($line)
{
 $ref = trim((string) ($line->product_ref ?? $line->ref ?? ''));
 $label = trim((string) ($line->label ?? ''));
 if ($label === '') $label = trim((string) ($line->product_label ?? $line->libelle ?? ''));
 $desc = cc_plain_line_text($line->desc ?? $line->description ?? '');

 $main = $ref;
 if ($label !== '') {
  $main .= ($main !== '' ? ' - ' : '').$label;
 }
 if ($main === '') return $desc;
 if ($desc !== '' && $desc !== $label && $desc !== $main) {
  $main .= "\n".$desc;
 }
 return $main;
}
if ($action==='save' && $user->hasRight('completioncertificate','write')) {
 if (!checkToken()) accessforbidden();
 $orderid=GETPOSTINT('orderid'); $order=new Commande($db); if($order->fetch($orderid)<=0) accessforbidden();
 $date=GETPOST('date_completion','alpha'); $note=GETPOST('note_public','restricthtml'); $ref=cc_ref($db);
 $db->begin();
 $sql="INSERT INTO ".MAIN_DB_PREFIX."completioncertificate(entity,ref,fk_soc,fk_commande,date_completion,note_public,status,fk_user_author,datec) VALUES(".(int)$conf->entity.",'".$db->escape($ref)."',".(int)$order->socid.",".(int)$order->id.",'".$db->escape($date)."','".$db->escape($note)."',0,".(int)$user->id.",'".$db->idate(dol_now())."')";
 if(!$db->query($sql)){ $db->rollback(); setEventMessages($db->lasterror(),null,'errors'); } else {
  $id=(int)$db->last_insert_id(MAIN_DB_PREFIX.'completioncertificate');
  $order->getLinesArray(); $ok=true;
  foreach($order->lines as $i=>$line){ $q=(float)GETPOST('qty_'.$line->id,'alphanohtml'); if($q<0) $q=0; if($q>(float)$line->qty) $q=(float)$line->qty;
   if($q==0) continue;
   $desc=cc_line_description($line);
   $sql="INSERT INTO ".MAIN_DB_PREFIX."completioncertificate_line(fk_completioncertificate,fk_commandedet,fk_product,description,qty_ordered,qty_certified,rang) VALUES(".$id.",".(int)$line->id.",".(!empty($line->fk_product)?(int)$line->fk_product:'NULL').",'".$db->escape($desc)."',".((float)$line->qty).",".$q.",".(int)$line->rang.")";
   if(!$db->query($sql)){ $ok=false; break; }
  }
  if($ok){$db->commit(); header('Location: '.dol_buildpath('/completioncertificate/card.php',1).'?id='.$id); exit;} else {$db->rollback(); setEventMessages($db->lasterror(),null,'errors');}
 }
}
if ($action==='validate' && $id && $user->hasRight('completioncertificate','write')) { if(!checkToken()) accessforbidden(); $db->query("UPDATE ".MAIN_DB_PREFIX."completioncertificate SET status=1,fk_user_valid=".(int)$user->id." WHERE rowid=".(int)$id." AND entity=".(int)$conf->entity); header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id); exit; }
llxHeader('',$langs->trans('CompletionCertificate'));
print load_fiche_titre($langs->trans('CompletionCertificate'),'','check-circle');
if ($orderid > 0 && !$id) {
 $order=new Commande($db); if($order->fetch($orderid)<=0) accessforbidden(); $order->fetch_thirdparty(); $order->getLinesArray();
 print '<form method="post" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="orderid" value="'.$order->id.'">';
 print '<table class="border centpercent"><tr><td class="titlefield">'.$langs->trans('Order').'</td><td>'.$order->getNomUrl(1).'</td></tr><tr><td>'.$langs->trans('ThirdParty').'</td><td>'.$order->thirdparty->getNomUrl(1).'</td></tr><tr><td>'.$langs->trans('CompletionDate').'</td><td><input type="date" name="date_completion" value="'.dol_print_date(dol_now(),'%Y-%m-%d').'"></td></tr></table><br>';
 print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Description').'</td><td class="right">'.$langs->trans('OrderedQty').'</td><td class="right">'.$langs->trans('CertifiedQty').'</td></tr>';
 foreach($order->lines as $line){$d=cc_line_description($line); print '<tr><td>'.dol_htmlentitiesbr($d).'</td><td class="right">'.price($line->qty).'</td><td class="right"><input class="width75 right" name="qty_'.$line->id.'" value="'.price2num($line->qty).'"></td></tr>';}
 print '</table><br><label>'.$langs->trans('NotePublic').'</label><br><textarea class="quatrevingtpercent" rows="4" name="note_public"></textarea><div class="center"><input class="button button-save" type="submit" value="'.$langs->trans('Create').'"></div></form>';
} elseif ($id) {
 $sql="SELECT c.*,s.nom socname,co.ref orderref FROM ".MAIN_DB_PREFIX."completioncertificate c JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=c.fk_soc JOIN ".MAIN_DB_PREFIX."commande co ON co.rowid=c.fk_commande WHERE c.rowid=".(int)$id." AND c.entity=".(int)$conf->entity;
 $r=$db->query($sql); $c=$r?$db->fetch_object($r):null; if(!$c) accessforbidden();
 print '<table class="border centpercent"><tr><td class="titlefield">'.$langs->trans('Ref').'</td><td>'.dol_escape_htmltag($c->ref).'</td></tr><tr><td>'.$langs->trans('ThirdParty').'</td><td>'.dol_escape_htmltag($c->socname).'</td></tr><tr><td>'.$langs->trans('Order').'</td><td>'.dol_escape_htmltag($c->orderref).'</td></tr><tr><td>'.$langs->trans('CompletionDate').'</td><td>'.dol_print_date($db->jdate($c->date_completion),'day').'</td></tr><tr><td>'.$langs->trans('Status').'</td><td>'.($c->status?$langs->trans('Validated'):$langs->trans('Draft')).'</td></tr></table><br>';
 $r=$db->query("SELECT * FROM ".MAIN_DB_PREFIX."completioncertificate_line WHERE fk_completioncertificate=".(int)$id." ORDER BY rang,rowid"); print '<table class="noborder centpercent"><tr class="liste_titre"><td>'.$langs->trans('Description').'</td><td class="right">'.$langs->trans('OrderedQty').'</td><td class="right">'.$langs->trans('CertifiedQty').'</td></tr>'; while($r && ($l=$db->fetch_object($r))) print '<tr><td>'.dol_htmlentitiesbr($l->description).'</td><td class="right">'.price($l->qty_ordered).'</td><td class="right">'.price($l->qty_certified).'</td></tr>'; print '</table>';
 print '<div class="tabsAction">'; if(!$c->status && $user->hasRight('completioncertificate','write')) print dolGetButtonAction('',$langs->trans('Validate'),'default',$_SERVER['PHP_SELF'].'?id='.$id.'&action=validate&token='.newToken(),''); if($c->status) print dolGetButtonAction('',$langs->trans('PDF'),'default',dol_buildpath('/completioncertificate/pdf.php',1).'?id='.$id,''); print '</div>';
 if(!empty($c->note_public)) print '<br><div class="opacitymedium">'.$langs->trans('NotePublic').'</div><div class="wordbreak">'.dol_htmlentitiesbr($c->note_public).'</div>';
}
llxFooter(); $db->close();
