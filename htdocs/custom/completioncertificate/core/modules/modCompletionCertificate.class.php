<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modCompletionCertificate extends DolibarrModules {
 public function __construct($db) {
  global $conf;
  $this->db=$db; $this->numero=581200; $this->rights_class='completioncertificate'; $this->family='crm';
  $this->module_position='90'; $this->name='CompletionCertificate'; $this->description='ModuleCompletionCertificateDesc';
  $this->version='0.1.1'; $this->const_name='MAIN_MODULE_COMPLETIONCERTIFICATE'; $this->picto='check-circle';
  $this->module_parts=array('hooks'=>array('ordercard')); $this->dirs=array('/completioncertificate/temp');
  $this->langfiles=array('completioncertificate@completioncertificate'); $this->phpmin=array(8,1); $this->need_dolibarr_version=array(23,0);
  $this->depends=array('modCommande'); $this->config_page_url=array(); $this->tabs=array(); $this->dictionaries=array(); $this->boxes=array(); $this->cronjobs=array();
  $this->rights = array(); $r = 0;
  $r++;
  $this->rights[$r][0] = 581201;
  $this->rights[$r][1] = 'Read completion certificates';
  $this->rights[$r][2] = 'r';
  $this->rights[$r][3] = 1;
  $this->rights[$r][4] = 'read';

  $r++;
  $this->rights[$r][0] = 581202;
  $this->rights[$r][1] = 'Create/modify completion certificates';
  $this->rights[$r][2] = 'w';
  $this->rights[$r][3] = 0;
  $this->rights[$r][4] = 'write';

  $r++;
  $this->rights[$r][0] = 581203;
  $this->rights[$r][1] = 'Delete completion certificates';
  $this->rights[$r][2] = 'd';
  $this->rights[$r][3] = 0;
  $this->rights[$r][4] = 'delete';
  $this->menu=array();
 }
 public function init($options='') {
  $result = $this->_load_tables('/completioncertificate/sql/');
  if ($result <= 0) return -1;
  $sql = array();
  return $this->_init($sql, $options);
 }
 public function remove($options='') { return $this->_remove(array(),$options); }
}
