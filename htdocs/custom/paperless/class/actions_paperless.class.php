<?php
/* Copyright (C) 2026 Krisztian Vanyolai
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

class ActionsPaperless extends CommonHookActions
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var string[] */
	public $errors = array();

	/** @var mixed[] */
	public $results = array();

	/** @var ?string */
	public $resprints;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Mark Dolibarr's standard attachment form so doActions can identify
	 * Paperless-routed uploads without relying on page-specific query parameters.
	 *
	 * @param array<string,mixed> $parameters Hook metadata
	 * @param CommonObject $object Current Dolibarr object
	 * @param ?string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int
	 */
	public function formattachOptionsUpload($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';

		if (!getDolGlobalInt('PAPERLESS_REDIRECT_PDF_UPLOADS', 1)) {
			return 0;
		}
		if (empty($parameters['perm'])) {
			return 0;
		}
		if (!is_object($object) || empty($object->id) || empty($object->element)) {
			return 0;
		}

		$this->resprints = '<script nonce="'.getNonce().'">'
			.'jQuery(function(){'
			.'var f=jQuery("#formuserfile");'
			.'if(f.length && !f.find("input[name=paperless_upload]").length){'
			.'f.append("<input type=\"hidden\" name=\"paperless_upload\" value=\"1\">");'
			.'}'
			.'});'
			.'</script>'
			.'<div class="opacitymedium small"><span class="fa fa-file-pdf"></span> Paperless-ngx: PDF routing active</div>';

		return 0;
	}

	/**
	 * Intercept standard attachment-form uploads for PDF-only submissions.
	 * Non-PDF uploads are deliberately left to Dolibarr core.
	 *
	 * @param array<string,mixed> $parameters Hook metadata
	 * @param CommonObject $object Current Dolibarr object
	 * @param ?string $action Current action
	 * @param HookManager $hookmanager Hook manager
	 * @return int <0 on error, 0 to continue core action, 1 to replace core action
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $permissiontoadd, $user;

		$this->resprints = '';
		$langs->load('paperless@paperless');

		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['userfile'])) {
			$currentContext = isset($parameters['currentcontext']) ? (string) $parameters['currentcontext'] : '';
			dol_syslog(
				'ActionsPaperless::doActions upload POST marker='.GETPOSTINT('paperless_upload').' context='.$currentContext,
				LOG_INFO
			);
		}

		if (!getDolGlobalInt('PAPERLESS_REDIRECT_PDF_UPLOADS', 1)) {
			return 0;
		}
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || GETPOSTINT('paperless_upload') !== 1) {
			return 0;
		}
		if (!getDolGlobalString('MAIN_UPLOAD_DOC')) {
			return 0;
		}
		if (!isset($_FILES['userfile']) || !is_array($_FILES['userfile'])) {
			return 0;
		}

		// Let the native page establish object-specific upload permissions first.
		if (!isset($permissiontoadd) || empty($permissiontoadd)) {
			return 0;
		}
		if (!is_object($object) || empty($object->id) || empty($object->element)) {
			return 0;
		}

		$currentContext = isset($parameters['currentcontext']) ? (string) $parameters['currentcontext'] : '';
		dol_syslog(
			'ActionsPaperless::doActions attachment candidate context='.$currentContext.
			' objecttype='.(string) $object->element.' objectid='.(int) $object->id,
			LOG_INFO
		);

		$files = $this->normalizeUserFiles($_FILES['userfile']);
		if (empty($files)) {
			return 0;
		}

		$hasPdf = false;
		$hasNonPdf = false;
		foreach ($files as $file) {
			if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) === 'pdf') {
				$hasPdf = true;
			} else {
				$hasNonPdf = true;
			}
		}

		if (!$hasPdf) {
			return 0;
		}
		if ($hasNonPdf) {
			setEventMessages($langs->trans('PaperlessMixedUploadFallback'), null, 'warnings');
			return 0;
		}

		$apiUrl = trim(getDolGlobalString('PAPERLESS_API_URL'));
		$webUrl = trim(getDolGlobalString('PAPERLESS_WEB_URL'));
		$apiToken = trim(getDolGlobalString('PAPERLESS_API_TOKEN'));
		$httpTimeout = max(1, getDolGlobalInt('PAPERLESS_HTTP_TIMEOUT', 30));

		if ($apiUrl === '' || $apiToken === '') {
			setEventMessages($langs->trans('PaperlessNotConfigured'), null, 'errors');
			return 1;
		}

		require_once __DIR__.'/paperlessclient.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

		$client = new PaperlessClient($apiUrl, $apiToken, $webUrl, $httpTimeout);
		$objectType = (string) $object->element;
		$objectId = (int) $object->id;

		$successCount = 0;
		foreach ($files as $file) {
			if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if ((int) $file['error'] !== UPLOAD_ERR_OK) {
				setEventMessages($langs->trans('PaperlessUploadPhpError', (string) $file['name'], (int) $file['error']), null, 'errors');
				continue;
			}

			$filename = dol_sanitizeFileName((string) $file['name'], '_', 0);
			$tmpPath = (string) $file['tmp_name'];
			if (!$this->isPdfFile($tmpPath, $filename)) {
				setEventMessages($langs->trans('PaperlessInvalidPdf', $filename), null, 'errors');
				continue;
			}

			$title = pathinfo($filename, PATHINFO_FILENAME);
			$taskId = $client->uploadDocument($tmpPath, $filename, $title);
			if ($taskId === false) {
				setEventMessages($langs->trans('PaperlessUploadFailed', $filename, $client->error), null, 'errors');
				continue;
			}

			$resolverBase = dol_buildpath('/paperless/open.php', 2);
			$link = new Link($this->db);
			$link->entity = isset($object->entity) && $object->entity ? (int) $object->entity : 0;
			$link->url = $resolverBase.'?task='.rawurlencode($taskId);
			$link->label = $filename.' (Paperless)';
			$link->objecttype = $objectType;
			$link->objectid = $objectId;

			$linkId = $link->create($user);
			if ($linkId <= 0) {
				setEventMessages($langs->trans('PaperlessLinkCreateFailed', $filename, $taskId), null, 'errors');
				continue;
			}

			// Add the link id after creation so the resolver can replace the queued URL
			// with a stable document-id URL after Paperless finishes consuming the file.
			$link->url = $resolverBase.'?task='.rawurlencode($taskId).'&linkid='.(int) $linkId;
			$link->update($user, 0);

			$successCount++;
			setEventMessages($langs->trans('PaperlessUploadQueued', $filename), null, 'mesgs');
		}

		dol_syslog('ActionsPaperless::doActions uploaded='.$successCount.' objecttype='.$objectType.' objectid='.$objectId, LOG_INFO);
		$action = '';

		// Always replace the core upload action for PDF-only submissions. On API error we must
		// not silently store a second, local copy in Dolibarr.
		return 1;
	}

	/**
	 * Normalize PHP's single/multiple upload shapes.
	 *
	 * @param array<string,mixed> $upload $_FILES['userfile']
	 * @return array<int,array{name:string,tmp_name:string,error:int,size:int,type:string}>
	 */
	private function normalizeUserFiles($upload)
	{
		$files = array();
		if (isset($upload['tmp_name']) && is_array($upload['tmp_name'])) {
			foreach ($upload['tmp_name'] as $key => $tmpName) {
				$files[] = array(
					'name' => isset($upload['name'][$key]) ? (string) $upload['name'][$key] : '',
					'tmp_name' => (string) $tmpName,
					'error' => isset($upload['error'][$key]) ? (int) $upload['error'][$key] : UPLOAD_ERR_NO_FILE,
					'size' => isset($upload['size'][$key]) ? (int) $upload['size'][$key] : 0,
					'type' => isset($upload['type'][$key]) ? (string) $upload['type'][$key] : '',
				);
			}
		} elseif (isset($upload['tmp_name'])) {
			$files[] = array(
				'name' => isset($upload['name']) ? (string) $upload['name'] : '',
				'tmp_name' => (string) $upload['tmp_name'],
				'error' => isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE,
				'size' => isset($upload['size']) ? (int) $upload['size'] : 0,
				'type' => isset($upload['type']) ? (string) $upload['type'] : '',
			);
		}
		return $files;
	}

	/**
	 * Validate both extension and PDF file signature.
	 *
	 * @param string $tmpPath Temporary file path
	 * @param string $filename Original/sanitized name
	 * @return bool
	 */
	private function isPdfFile($tmpPath, $filename)
	{
		if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf' || !is_readable($tmpPath)) {
			return false;
		}
		$handle = @fopen($tmpPath, 'rb');
		if ($handle === false) {
			return false;
		}
		$signature = fread($handle, 5);
		fclose($handle);
		return $signature === '%PDF-';
	}
}
