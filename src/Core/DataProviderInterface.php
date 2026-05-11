<?php

declare(strict_types=1);

/**
 * This file is part of the DigestModules package
 *
 * https://github.com/VitexSoftware/DigestModules/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace VitexSoftware\DigestModules\Core;

/**
 * Interface for data providers (accounting systems)
 *
 * Data providers abstract the connection to different accounting systems
 * like AbraFlexi, Pohoda, Money S3, etc.
 *
 * Standardized entity names used across all providers:
 * - ENTITY_OUTCOMING_INVOICES — issued/outgoing invoices
 * - ENTITY_INCOMING_INVOICES — received/incoming invoices
 * - ENTITY_BANK_STATEMENTS — bank transactions (payments in/out)
 * - ENTITY_CONTACTS — address book / customers / suppliers
 * - ENTITY_PRODUCTS — product catalog / price list
 * - ENTITY_REMINDERS — payment reminders sent
 * - ENTITY_ORDERS — received orders
 *
 * Providers MUST return records with neutral field names (FIELD_* constants).
 * Providers MUST handle neutral condition keys (FILTER_* constants).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
interface DataProviderInterface
{
    // ── Entity type constants ──────────────────────────────────────────────
    public const ENTITY_OUTCOMING_INVOICES = 'outcoming_invoices';
    public const ENTITY_INCOMING_INVOICES  = 'incoming_invoices';
    public const ENTITY_BANK_STATEMENTS    = 'bank_statements';
    public const ENTITY_CONTACTS           = 'contacts';
    public const ENTITY_PRODUCTS           = 'products';
    public const ENTITY_REMINDERS          = 'reminders';
    public const ENTITY_ORDERS             = 'orders';

    // ── Neutral filter/condition keys ──────────────────────────────────────
    /** Date range filter: ['column' => DATE_COLUMN_*, 'period' => \DatePeriod] */
    public const FILTER_DATE_PERIOD           = 'date_period';
    /** Payment direction: 'incoming' | 'outgoing' */
    public const FILTER_PAYMENT_DIRECTION     = 'direction';
    /** Payment status: 'unpaid' | 'partial' | 'paid' | 'unpaid_or_partial' */
    public const FILTER_PAYMENT_STATUS        = 'payment_status';
    /** Whether to include cancelled documents (bool) */
    public const FILTER_CANCELLED             = 'cancelled';
    /** Whether document is posted/accounted (bool) */
    public const FILTER_ACCOUNTED             = 'accounted';
    /** Whether payment is matched to an invoice (bool) */
    public const FILTER_MATCHED               = 'matched';
    /** Document type: 'invoice' | 'proforma' | 'credit_note' */
    public const FILTER_DOCUMENT_TYPE         = 'document_type';
    /** Exclude a document type: 'credit_note' */
    public const FILTER_EXCLUDE_DOCUMENT_TYPE = 'exclude_document_type';
    /** Only records where outgoing email has not been sent (bool true) */
    public const FILTER_MAIL_PENDING          = 'mail_pending';
    /** Only contacts missing an email address (bool true) */
    public const FILTER_MISSING_EMAIL         = 'missing_email';
    /** Only contacts missing a phone number (bool true) */
    public const FILTER_MISSING_PHONE         = 'missing_phone';
    /** Include invoice line items in result (bool true) */
    public const FILTER_WITH_ITEMS            = 'with_items';
    /** Only documents with a past due date (bool true) */
    public const FILTER_OVERDUE               = 'overdue';
    /** Only products that have a purchase/buy price set (bool true) */
    public const FILTER_HAS_BUY_PRICE         = 'has_buy_price';
    /** Only products that have a selling price set (bool true) */
    public const FILTER_HAS_SELL_PRICE        = 'has_sell_price';
    /** Contact relationship type: 'customer' | 'supplier' */
    public const FILTER_RELATIONSHIP          = 'relationship';
    /** Maximum number of records to return (int) */
    public const FILTER_LIMIT                 = 'limit';

    // ── Neutral date column names (used inside FILTER_DATE_PERIOD) ─────────
    public const DATE_COLUMN_ISSUE_DATE       = 'issue_date';
    public const DATE_COLUMN_DUE_DATE         = 'due_date';
    public const DATE_COLUMN_LAST_UPDATED     = 'last_updated';
    public const DATE_COLUMN_FIRST_REMINDER   = 'first_reminder_date';

    // ── Neutral field names returned in each record ────────────────────────
    public const FIELD_CODE                   = 'code';
    public const FIELD_DATE                   = 'date';
    public const FIELD_DUE_DATE               = 'due_date';
    public const FIELD_COMPANY                = 'company';
    public const FIELD_TOTAL_AMOUNT           = 'total_amount';
    public const FIELD_TOTAL_AMOUNT_FOREIGN   = 'total_amount_foreign';
    public const FIELD_REMAINING_AMOUNT       = 'remaining_amount';
    public const FIELD_REMAINING_AMOUNT_FOREIGN = 'remaining_amount_foreign';
    public const FIELD_DEPOSIT_AMOUNT         = 'deposit_amount';
    public const FIELD_DEPOSIT_AMOUNT_FOREIGN = 'deposit_amount_foreign';
    public const FIELD_CURRENCY               = 'currency';
    public const FIELD_CANCELLED              = 'cancelled';
    public const FIELD_DOCUMENT_TYPE          = 'document_type';
    public const FIELD_PAYMENT_STATUS         = 'payment_status';
    public const FIELD_MAIL_STATUS            = 'mail_status';
    public const FIELD_DESCRIPTION            = 'description';
    public const FIELD_CONTACT_EMAIL          = 'contact_email';
    public const FIELD_DEDUCTION_STATUS       = 'deduction_status';
    public const FIELD_FIRST_REMINDER_DATE    = 'first_reminder_date';
    public const FIELD_SECOND_REMINDER_DATE   = 'second_reminder_date';
    public const FIELD_PRE_LITIGATION_DATE    = 'pre_litigation_date';
    public const FIELD_ITEMS                  = 'items';
    public const FIELD_DIRECTION              = 'direction';
    public const FIELD_BANK_ACCOUNT           = 'bank_account';
    public const FIELD_ACCOUNTED              = 'accounted';
    public const FIELD_MATCHED                = 'matched';
    public const FIELD_NAME                   = 'name';
    public const FIELD_EMAIL                  = 'email';
    public const FIELD_PHONE                  = 'phone';
    public const FIELD_STREET                 = 'street';
    public const FIELD_CITY                   = 'city';
    public const FIELD_BUY_PRICE              = 'buy_price';
    public const FIELD_SELL_PRICE             = 'sell_price';

    // ── Neutral field values (payment_status) ──────────────────────────────
    public const PAYMENT_STATUS_UNPAID           = 'unpaid';
    public const PAYMENT_STATUS_PARTIAL          = 'partial';
    public const PAYMENT_STATUS_PAID             = 'paid';
    public const PAYMENT_STATUS_UNPAID_OR_PARTIAL = 'unpaid_or_partial';

    // ── Neutral field values (mail_status) ─────────────────────────────────
    public const MAIL_STATUS_SENT    = 'sent';
    public const MAIL_STATUS_PENDING = 'pending';
    public const MAIL_STATUS_EMPTY   = 'empty';

    // ── Neutral field values (document_type) ───────────────────────────────
    public const DOCUMENT_TYPE_INVOICE     = 'invoice';
    public const DOCUMENT_TYPE_PROFORMA    = 'proforma';
    public const DOCUMENT_TYPE_CREDIT_NOTE = 'credit_note';

    // ── Neutral field values (deduction_status) ────────────────────────────
    public const DEDUCTION_STATUS_NONE                 = 'none';
    public const DEDUCTION_STATUS_PARTIAL              = 'partial';
    public const DEDUCTION_STATUS_COMPLETE             = 'complete';
    public const DEDUCTION_STATUS_TAX_DOCUMENT_CREATED = 'tax_document_created';

    // ── Neutral field values (direction) ───────────────────────────────────
    public const DIRECTION_INCOMING = 'incoming';
    public const DIRECTION_OUTGOING = 'outgoing';

    // ── Neutral field values (item_type inside items[]) ────────────────────
    public const ITEM_TYPE_CATALOG = 'catalog';
    public const ITEM_TYPE_TEXT    = 'text';

    /**
     * Get data from the accounting system.
     *
     * The $conditions array uses FILTER_* constants as keys.
     * The returned records use FIELD_* constants as field names.
     * The $columns parameter is ignored by providers that implement full
     * normalization; pass [] unless backwards compatibility is required.
     *
     * @param string               $entity     Entity type (ENTITY_* constant)
     * @param array<string, mixed> $conditions Neutral filter conditions
     * @param array<string>        $columns    Deprecated — providers return neutral schema
     * @return array<array<string, mixed>> Normalized records
     */
    public function getData(string $entity, array $conditions = [], array $columns = []): array;

    /**
     * Get system name/type
     *
     * @return string Name of the accounting system (abraflexi, pohoda, etc.)
     */
    public function getSystemName(): string;

    /**
     * Get supported entities
     *
     * @return array<string> List of supported entity types
     */
    public function getSupportedEntities(): array;

    /**
     * Check if provider supports a specific feature
     *
     * @param string $feature Feature name
     * @return bool Whether the feature is supported
     */
    public function supportsFeature(string $feature): bool;

    /**
     * Get company information
     *
     * @return array<string, mixed> Company details
     */
    public function getCompanyInfo(): array;

    /**
     * Format date for the accounting system
     *
     * @param \DateTime $date Date to format
     * @return string Formatted date string
     */
    public function formatDate(\DateTime $date): string;

    /**
     * Format date period condition for queries
     *
     * @param string      $column Date column name (neutral DATE_COLUMN_* constant)
     * @param \DatePeriod $period Time period
     * @return string Formatted condition string for this provider
     */
    public function formatDatePeriod(string $column, \DatePeriod $period): string;
}
