<?php
/* Copyright (C) 2026 Vanyolai Krisztián <vanyolai@gmail.com> */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

/**
 * Parent class for completion certificate document models.
 */
abstract class ModelePDFCertificate extends CommonDocGenerator
{
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		return getListOfModels($db, 'certificate', $maxfilenamelength);
	}

	abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);
}
