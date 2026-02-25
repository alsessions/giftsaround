<?php
/**
 * Migration script to copy businessAddress data from the addresses table
 * into elements_sites.content as plain text (Craft 5 JSON content format).
 *
 * Intended when converting the field from Addresses -> PlainText.
 * Run from project root with: ddev craft php-script migrate-addresses.php
 */

use craft\helpers\Console;

// Dry-run controls:
// - define('MIGRATE_ADDRESS_DRY_RUN', true) before requiring this script
// - set env var MIGRATE_ADDRESSES_DRY_RUN=1
// - pass --dry-run when run directly via PHP CLI
$argv = $_SERVER['argv'] ?? [];
$argDryRun = in_array('--dry-run', $argv, true);
$envDryRun = filter_var(getenv('MIGRATE_ADDRESSES_DRY_RUN') ?: false, FILTER_VALIDATE_BOOLEAN);
$constDryRun = defined('MIGRATE_ADDRESS_DRY_RUN') && MIGRATE_ADDRESS_DRY_RUN;
$dryRun = $argDryRun || $envDryRun || $constDryRun;

$field = Craft::$app->fields->getFieldByHandle('businessAddress');
if (!$field) {
    Console::output("ERROR: businessAddress field not found!");
    return 1;
}

$fieldId = $field->id;
$fieldUid = $field->uid;
$jsonPath = '$."' . $fieldUid . '"';

Console::output("Starting businessAddress migration...\n");
Console::output("Field ID: {$fieldId}");
Console::output("Field UID: {$fieldUid}\n");
Console::output("Mode: " . ($dryRun ? "DRY RUN (no writes)" : "LIVE (writes enabled)") . "\n");

// Get all entry-owned addresses from the addresses table for this field.
$addresses = Craft::$app->db->createCommand("
    SELECT
        a.id,
        a.primaryOwnerId,
        a.addressLine1,
        a.addressLine2,
        a.locality,
        a.administrativeArea,
        a.postalCode,
        a.countryCode
    FROM addresses a
    INNER JOIN entries en ON en.id = a.primaryOwnerId
    WHERE a.fieldId = :fieldId
    AND a.primaryOwnerId IS NOT NULL
", [':fieldId' => $fieldId])->queryAll();

Console::output("Found " . count($addresses) . " addresses to migrate.\n");

// Pick the "best" address row per owner to avoid last-write-wins when duplicates exist.
$bestAddresses = [];
foreach ($addresses as $row) {
    $ownerId = (int)$row['primaryOwnerId'];
    $score = 0;
    $score += !empty($row['addressLine1']) ? 4 : 0;
    $score += !empty($row['locality']) ? 3 : 0;
    $score += !empty($row['administrativeArea']) ? 2 : 0;
    $score += !empty($row['postalCode']) ? 2 : 0;
    $score += !empty($row['addressLine2']) ? 1 : 0;

    if (!isset($bestAddresses[$ownerId]) || $score > $bestAddresses[$ownerId]['_score']) {
        $row['_score'] = $score;
        $bestAddresses[$ownerId] = $row;
    }
}

Console::output("Using " . count($bestAddresses) . " best-owner address rows after dedupe.\n");

$migratedCount = 0;
$wouldUpdateCount = 0;
$errorCount = 0;

foreach ($bestAddresses as $addressData) {
    $ownerId = $addressData['primaryOwnerId'];

    // Build the plain text address
    $addressLines = [];

    if (!empty($addressData['addressLine1'])) {
        $addressLines[] = $addressData['addressLine1'];
    }

    if (!empty($addressData['addressLine2'])) {
        $addressLines[] = $addressData['addressLine2'];
    }

    // City, State ZIP
    $cityStateZip = [];
    if (!empty($addressData['locality'])) {
        $cityStateZip[] = $addressData['locality'];
    }
    if (!empty($addressData['administrativeArea'])) {
        $cityStateZip[] = $addressData['administrativeArea'];
    }
    if (!empty($addressData['postalCode'])) {
        $cityStateZip[] = $addressData['postalCode'];
    }

    if (!empty($cityStateZip)) {
        $addressLines[] = implode(', ', $cityStateZip);
    }

    if (empty($addressLines)) {
        Console::output("⚠ Skipping entry ID {$ownerId} - no address data");
        continue;
    }

    $plainTextAddress = implode("\n", $addressLines);

    try {
        $existing = Craft::$app->db->createCommand("
            SELECT JSON_UNQUOTE(JSON_EXTRACT(content, :jsonPath))
            FROM elements_sites
            WHERE elementId = :ownerId
            LIMIT 1
        ", [
            ':jsonPath' => $jsonPath,
            ':ownerId' => $ownerId,
        ])->queryScalar();

        if ($dryRun) {
            if ((string)$existing !== (string)$plainTextAddress) {
                $wouldUpdateCount++;
                Console::output("~ Would update entry ID {$ownerId}");
            } else {
                Console::output("= No change for entry ID {$ownerId}");
            }
            Console::output("  Address: " . str_replace("\n", " | ", $plainTextAddress));
            continue;
        }

        // Write value by field UID into all sites for this element.
        $updatedRows = Craft::$app->db->createCommand("
            UPDATE elements_sites
            SET content = JSON_SET(COALESCE(content, '{}'), :jsonPath, :addressText)
            WHERE elementId = :ownerId
        ", [
            ':jsonPath' => $jsonPath,
            ':addressText' => $plainTextAddress,
            ':ownerId' => $ownerId,
        ])->execute();

        $migratedCount++;
        Console::output("✓ Migrated entry ID {$ownerId}, sites updated: {$updatedRows}");
        Console::output("  Address: " . str_replace("\n", " | ", $plainTextAddress));
    } catch (\Throwable $e) {
        $errorCount++;
        Console::output("✗ ERROR migrating entry ID {$ownerId}");
        Console::output("  Exception: " . $e->getMessage());
    }
}

Console::output("\n" . str_repeat("=", 60));
Console::output("Migration complete!");
Console::output("Successfully migrated: {$migratedCount}");
if ($dryRun) {
    Console::output("Would update: {$wouldUpdateCount}");
}
Console::output("Errors: {$errorCount}");
Console::output(str_repeat("=", 60));

return 0;
