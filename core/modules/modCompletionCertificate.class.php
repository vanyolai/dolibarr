<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modCompletionCertificate extends DolibarrModules {
 public function __construct($db) {
  global $conf;
  $this->db=$db; $this->numero=581200; $this->rights_class='completioncertificate'; $this->family='crm';
  $this->module_position='90'; $this->name='CompletionCertificate'; $this->description='ModuleCompletionCertificateDesc';
  $this->version='0.1.0'; $this->const_name='MAIN_MODULE_COMPLETIONCERTIFICATE'; $this->picto='check-circle';
  $this->module_parts=array('hooks'=>array('ordercard')); $this->dirs=array('/completioncertificate/temp');
  $this->langfiles=array('completioncertificate@completioncertificate'); $this->phpmin=array(8,1); $this->need_dolibarr_version=array(23,0);
  $this->depends=array('modCommande'); $this->config_page_url=array(); $this->tabs=array(); $this->dictionaries=array(); $this->boxes=array(); $this->cronjobs=array();
  $this->rights=array(); $r=0;
  $this->rights[$r++]=array(581201,'Read completion certificates',1,'read');
  $this->rights[$r++]=array(581202,'Create/modify completion certificates',0,'write');
  $this->rights[$r++]=array(581203,'Delete completion certificates',0,'delete');
  $this->menu=array();
 }
 public function init($options='') { return $this->_init(array('/completioncertificate/sql/llx_completioncertificate.sql','/completioncertificate/sql/llx_completioncertificate_line.sql'),$options); }
 public function remove($options='') { return $this->_remove(array(),$options); }
}
