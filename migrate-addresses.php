<?php
/**
 * Migration script to convert businessAddress from Address field to Plain Text
 * Run with: ddev craft exec/exec migrate-addresses.php
 */

use craft\elements\User;
use craft\helpers\Console;

$field = Craft::$app->fields->getFieldByHandle('businessAddress');
if (!$field) {
    Console::output("ERROR: businessAddress field not found!");
    return 1;
}

$fieldId = $field->id;

Console::output("Starting businessAddress migration...\n");

// Get all addresses from the addresses table for this field
$addresses = Craft::$app->db->createCommand("
    SELECT
        a.primaryOwnerId,
        a.addressLine1,
        a.addressLine2,
        a.locality,
        a.administrativeArea,
        a.postalCode,
        a.countryCode
    FROM addresses a
    WHERE a.fieldId = :fieldId
    AND a.primaryOwnerId IS NOT NULL
", [':fieldId' => $fieldId])->queryAll();

Console::output("Found " . count($addresses) . " addresses to migrate.\n");

$migratedCount = 0;
$errorCount = 0;

foreach ($addresses as $addressData) {
    $ownerId = $addressData['primaryOwnerId'];

    // Check if this is a user (business user)
    $user = User::find()->id($ownerId)->one();

    if (!$user) {
        Console::output("⚠ Skipping owner ID {$ownerId} - not a user");
        continue;
    }

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
        Console::output("⚠ Skipping user {$user->username} (ID: {$ownerId}) - no address data");
        continue;
    }

    $plainTextAddress = implode("\n", $addressLines);

    // Update the user's businessAddress field
    $user->setFieldValue('businessAddress', $plainTextAddress);

    if (Craft::$app->elements->saveElement($user)) {
        $migratedCount++;
        Console::output("✓ Migrated address for user: {$user->username} (ID: {$ownerId})");
        Console::output("  Address: " . str_replace("\n", " | ", $plainTextAddress));
    } else {
        $errorCount++;
        Console::output("✗ ERROR migrating user: {$user->username} (ID: {$ownerId})");
        if ($user->hasErrors()) {
            Console::output("  Errors: " . json_encode($user->getErrors()));
        }
    }
}

Console::output("\n" . str_repeat("=", 60));
Console::output("Migration complete!");
Console::output("Successfully migrated: {$migratedCount}");
Console::output("Errors: {$errorCount}");
Console::output(str_repeat("=", 60));

return 0;
