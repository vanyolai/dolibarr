<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
class ActionsCompletionCertificate extends CommonHookActions {
 public $db;
 public function __construct($db){$this->db=$db;}
 public function addMoreActionsButtons($parameters,&$object,&$action,$hookmanager) {
  global $langs,$user;
  if (($parameters['currentcontext'] ?? '') !== 'ordercard' || empty($object->id) || $object->status <= 0) return 0;
  if (!$user->hasRight('completioncertificate','write')) return 0;
  $langs->load('completioncertificate@completioncertificate');
  print dolGetButtonAction('', $langs->trans('CreateCompletionCertificate'), 'default',
   dol_buildpath('/completioncertificate/card.php',1).'?action=create&orderid='.(int)$object->id, '');
  return 0;
 }
}
