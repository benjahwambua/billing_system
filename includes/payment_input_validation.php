<?php
/**
 * Server-side validation for manually recorded customer payments.
 * Future-dated payments are rejected to keep cashbook and reconciliation reports reliable.
 */
if (!function_exists('flexihubValidateManualPaymentInput')) {
    function flexihubValidateManualPaymentInput($method, $date, $reference = '', $today = null)
    {
        $errors = [];
        $allowedMethods = ['cash', 'mpesa', 'bank', 'card'];
        $method = strtolower(trim((string)$method));
        $date = trim((string)$date);
        $reference = trim((string)$reference);

        if (!in_array($method, $allowedMethods, true)) {
            $errors[] = 'Select a valid payment method.';
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validDate = $parsedDate instanceof DateTimeImmutable
            && $parsedDate->format('Y-m-d') === $date
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));

        if (!$validDate) {
            $errors[] = 'Enter a valid payment date.';
        } else {
            $today = $today === null ? date('Y-m-d') : (string)$today;
            if ($date > $today) {
                $errors[] = 'Payment date cannot be in the future.';
            }
        }

        if (strlen($reference) > 100) {
            $errors[] = 'Payment reference must not exceed 100 characters.';
        }

        return $errors;
    }
}
