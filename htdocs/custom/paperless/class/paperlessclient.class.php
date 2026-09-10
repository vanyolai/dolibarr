<?php
/* Copyright (C) 2026 Krisztian Vanyolai
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Small server-side client for the Paperless-ngx REST API.
 */
class PaperlessClient
{
	/** @var string */
	private $apiUrl;

	/** @var string */
	private $webUrl;

	/** @var string */
	private $token;

	/** @var int */
	private $timeout;

	/** @var string */
	public $error = '';

	/** @var string[] */
	public $errors = array();

	/**
	 * @param string $apiUrl Paperless-ngx base URL
	 * @param string $token API token
	 * @param string $webUrl Browser-facing base URL, defaults to $apiUrl
	 * @param int $timeout HTTP timeout in seconds
	 */
	public function __construct($apiUrl, $token, $webUrl = '', $timeout = 30)
	{
		$this->apiUrl = rtrim(trim($apiUrl), '/');
		$this->webUrl = rtrim(trim($webUrl !== '' ? $webUrl : $apiUrl), '/');
		$this->token = trim($token);
		$this->timeout = max(1, (int) $timeout);
	}

	/**
	 * Upload a PDF to Paperless-ngx.
	 *
	 * Paperless returns a consumption task UUID, not the final document ID.
	 *
	 * @param string $tmpPath PHP upload temporary path
	 * @param string $filename Original file name
	 * @param string $title Optional document title
	 * @return string|false Task UUID on success, false on error
	 */
	public function uploadDocument($tmpPath, $filename, $title = '')
	{
		$this->clearError();

		if (!is_file($tmpPath) || !is_readable($tmpPath)) {
			return $this->fail('Uploaded temporary file is not readable.');
		}

		if (!class_exists('CURLFile')) {
			return $this->fail('PHP cURL file upload support is not available.');
		}

		$fields = array(
			'document' => new CURLFile($tmpPath, 'application/pdf', $filename),
		);
		if ($title !== '') {
			$fields['title'] = $title;
		}

		$response = $this->request('POST', '/api/documents/post_document/', $fields);
		if ($response === false) {
			return false;
		}

		if (is_string($response) && $this->isTaskId($response)) {
			return $response;
		}
		if (is_array($response)) {
			foreach (array('task_id', 'id') as $key) {
				if (!empty($response[$key]) && is_string($response[$key]) && $this->isTaskId($response[$key])) {
					return $response[$key];
				}
			}
		}

		return $this->fail('Paperless-ngx accepted the request but did not return a valid consumption task UUID.');
	}

	/**
	 * Get one consumption task by UUID.
	 * Supports both the paginated API v10 response and the older array response.
	 *
	 * @param string $taskId Task UUID
	 * @return array<string,mixed>|null|false Task, null while not visible yet, false on API error
	 */
	public function getTask($taskId)
	{
		$this->clearError();

		if (!$this->isTaskId($taskId)) {
			return $this->fail('Invalid Paperless-ngx task UUID.');
		}

		$response = $this->request('GET', '/api/tasks/?task_id='.rawurlencode($taskId));
		if ($response === false) {
			return false;
		}

		$tasks = array();
		if (is_array($response) && isset($response['results']) && is_array($response['results'])) {
			$tasks = $response['results'];
		} elseif (is_array($response)) {
			$tasks = $response;
		}

		foreach ($tasks as $task) {
			if (is_array($task) && isset($task['task_id']) && (string) $task['task_id'] === $taskId) {
				return $task;
			}
		}

		return null;
	}

	/**
	 * Extract final Paperless document ID from a task if consumption has completed.
	 *
	 * @param string $taskId Task UUID
	 * @return int|false 0 if still pending/not visible, positive document ID on success, false on failure/API error
	 */
	public function resolveDocumentId($taskId)
	{
		$task = $this->getTask($taskId);
		if ($task === false) {
			return false;
		}
		if ($task === null) {
			return 0;
		}

		$status = strtoupper((string) ($task['status'] ?? ''));
		if ($status === 'SUCCESS') {
			$documentId = (int) ($task['related_document'] ?? 0);
			if ($documentId > 0) {
				return $documentId;
			}
			return $this->fail('Paperless-ngx reports success, but no related document ID was returned.');
		}

		if ($status === 'FAILURE' || $status === 'FAILED') {
			$result = trim((string) ($task['result'] ?? ''));
			return $this->fail('Paperless-ngx document consumption failed'.($result !== '' ? ': '.$result : '.'));
		}

		return 0;
	}

	/**
	 * @param int $documentId Paperless document ID
	 * @return string Browser URL
	 */
	public function getDocumentUrl($documentId)
	{
		return $this->webUrl.'/documents/'.((int) $documentId).'/details';
	}

	/**
	 * @param string $method HTTP method
	 * @param string $path API path including optional query string
	 * @param array<string,mixed>|null $postFields Multipart POST fields
	 * @return mixed|false Decoded JSON response, or false on error
	 */
	private function request($method, $path, $postFields = null)
	{
		if ($this->apiUrl === '' || $this->token === '') {
			return $this->fail('Paperless-ngx API URL or API token is not configured.');
		}
		if (!function_exists('curl_init')) {
			return $this->fail('PHP cURL extension is required for the Paperless-ngx integration.');
		}

		$ch = curl_init($this->apiUrl.$path);
		if ($ch === false) {
			return $this->fail('Could not initialize cURL.');
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeout));
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Authorization: Token '.$this->token,
			'Accept: application/json; version=10',
		));

		if (strtoupper($method) === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		}

		$body = curl_exec($ch);
		$curlError = curl_error($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($body === false) {
			return $this->fail('Paperless-ngx request failed: '.$curlError);
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			$detail = trim((string) $body);
			if (strlen($detail) > 500) {
				$detail = substr($detail, 0, 500).'...';
			}
			return $this->fail('Paperless-ngx returned HTTP '.$httpCode.($detail !== '' ? ': '.$detail : ''));
		}

		$decoded = json_decode((string) $body, true);
		if (json_last_error() === JSON_ERROR_NONE) {
			return $decoded;
		}

		return $this->fail('Paperless-ngx returned an invalid JSON response.');
	}

	/** @return void */
	private function clearError()
	{
		$this->error = '';
		$this->errors = array();
	}

	/**
	 * @param string $message Error message
	 * @return false
	 */
	private function fail($message)
	{
		$this->error = $message;
		$this->errors[] = $message;
		return false;
	}

	/**
	 * @param mixed $value Candidate UUID
	 * @return bool
	 */
	private function isTaskId($value)
	{
		return is_string($value) && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
	}
}
