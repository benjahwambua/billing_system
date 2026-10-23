-- Read-only audit before database/036_mpesa_receipt_claims.sql.
-- Run in phpMyAdmin with billing_system selected. Resolve all rows returned by the first query.
-- This script does not change schema or data.

SELECT receipt, COUNT(*) AS occurrences,
       GROUP_CONCAT(CONCAT(source_type, '#', source_transaction_id,
                           ' (tenant ', tenant_id, ')')
                    ORDER BY source_type, source_transaction_id SEPARATOR '; ') AS sources
FROM (
    SELECT TRIM(provider_receipt) AS receipt, tenant_id,
           'gateway_transaction' AS source_type, id AS source_transaction_id
    FROM payment_gateway_transactions
    WHERE provider = 'mpesa' AND provider_receipt IS NOT NULL
      AND TRIM(provider_receipt) <> ''

    UNION ALL

    SELECT TRIM(pp.external_transaction_id) AS receipt, pp.tenant_id,
           'platform_payment' AS source_type, pp.id AS source_transaction_id
    FROM platform_payments pp
    WHERE pp.provider = 'mpesa' AND pp.external_transaction_id IS NOT NULL
      AND TRIM(pp.external_transaction_id) <> ''
      -- The tenant gateway callback records the same SaaS payment in both ledgers.
      -- Count the mirrored platform_payments row only when it has no matching
      -- platform-flow gateway transaction for the same tenant, receipt and invoice.
      AND NOT EXISTS (
          SELECT 1
          FROM payment_gateway_transactions pgt
          JOIN platform_invoices pi
            ON pi.tenant_id = pgt.tenant_id
           AND (pi.id = pgt.platform_invoice_id
                OR (pgt.platform_invoice_id IS NULL AND pi.invoice_number = pgt.account_reference))
          WHERE pgt.provider = 'mpesa'
            AND pgt.flow = 'platform'
            AND pgt.tenant_id = pp.tenant_id
            AND TRIM(pgt.provider_receipt) = TRIM(pp.external_transaction_id)
            AND pi.id = pp.invoice_id
      )
) AS all_receipts
GROUP BY receipt
HAVING COUNT(*) > 1
ORDER BY occurrences DESC, receipt;

-- Receipts too long for the claim key must be manually reviewed before migration.
SELECT 'payment_gateway_transactions' AS source_table, id, provider_receipt AS receipt
FROM payment_gateway_transactions
WHERE provider='mpesa' AND provider_receipt IS NOT NULL
  AND CHAR_LENGTH(TRIM(provider_receipt)) > 191
UNION ALL
SELECT 'platform_payments' AS source_table, id, external_transaction_id AS receipt
FROM platform_payments
WHERE provider='mpesa' AND external_transaction_id IS NOT NULL
  AND CHAR_LENGTH(TRIM(external_transaction_id)) > 191;
