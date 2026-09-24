<?php
/* Backward-compatible class name for early 0.x versions. */

require_once __DIR__.'/certificate.class.php';

if (!class_exists('CompletionCertificate', false)) {
	class CompletionCertificate extends Certificate
	{
	}
}
