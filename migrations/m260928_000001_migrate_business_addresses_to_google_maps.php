<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Addresses;
use doublesecretagency\googlemaps\fields\AddressField;
use doublesecretagency\googlemaps\helpers\GoogleMaps;
use Throwable;

class m260928_000001_migrate_business_addresses_to_google_maps extends Migration
{
    public function up(bool $throwExceptions = false): bool
    {
        if (!Craft::$app->getPlugins()->isPluginEnabled('google-maps')) {
            echo "Google Maps must be installed and enabled.\n";
            return false;
        }

        if (!GoogleMaps::getServerKey() || !GoogleMaps::getBrowserKey()) {
            echo "Google Maps browser and server API keys must be configured.\n";
            return false;
        }

        $sourceField = Craft::$app->getFields()->getFieldByHandle('businessAddress');
        $targetField = Craft::$app->getFields()->getFieldByHandle('address');

        if (!$sourceField instanceof Addresses || !$targetField instanceof AddressField) {
            echo "The businessAddress and address fields are not configured with the expected types.\n";
            return false;
        }

        $entries = Entry::find()
            ->section('business')
            ->type('business')
            ->status(null)
            ->siteId(Craft::$app->getSites()->getPrimarySite()->id)
            ->drafts(false)
            ->revisions(false)
            ->all();

        $migrated = 0;
        $skipped = 0;
        $missing = 0;
        $failed = [];

        foreach ($entries as $entry) {
            $currentAddress = $entry->getFieldValue($targetField->handle);

            if ($currentAddress && !$currentAddress->isEmpty()) {
                $skipped++;
                continue;
            }

            $legacyAddresses = $entry->getFieldValue($sourceField->handle)->all();

            if (!$legacyAddresses) {
                $missing++;
                echo "Skipping entry {$entry->id} ({$entry->title}): no legacy address.\n";
                continue;
            }

            if (count($legacyAddresses) > 1) {
                $failed[] = $entry->id;
                echo "Failed entry {$entry->id} ({$entry->title}): multiple active legacy addresses require review.\n";
                continue;
            }

            $legacyAddress = $legacyAddresses[0];

            $query = $this->addressQuery($legacyAddress);

            try {
                $lookup = GoogleMaps::lookup($query);
                $googleAddress = $lookup->one();

                if (!$googleAddress || !$googleAddress->hasCoords()) {
                    $failed[] = $entry->id;
                    $error = $lookup->error ?: 'no result with coordinates';
                    echo "Failed entry {$entry->id} ({$entry->title}): {$error}.\n";
                    continue;
                }

                if (!$this->sameCountry($legacyAddress->countryCode, $googleAddress->countryCode)) {
                    $failed[] = $entry->id;
                    echo "Failed entry {$entry->id} ({$entry->title}): geocoder returned a different country.\n";
                    continue;
                }

                $entry->setFieldValue($targetField->handle, [
                    'formatted' => $googleAddress->formatted,
                    'raw' => $googleAddress->raw,
                    'name' => $googleAddress->name,
                    'street1' => $legacyAddress->addressLine1,
                    'street2' => $legacyAddress->addressLine2,
                    'city' => $legacyAddress->locality,
                    'state' => $legacyAddress->administrativeArea,
                    'zip' => $legacyAddress->postalCode,
                    'neighborhood' => $googleAddress->neighborhood,
                    'county' => $googleAddress->county,
                    'country' => $googleAddress->country,
                    'countryCode' => $legacyAddress->countryCode ?: $googleAddress->countryCode,
                    'placeId' => $googleAddress->placeId,
                    'lat' => $googleAddress->lat,
                    'lng' => $googleAddress->lng,
                    'zoom' => 16,
                ]);

                if (!Craft::$app->getElements()->saveElement($entry, false, true, true)) {
                    $failed[] = $entry->id;
                    echo "Failed entry {$entry->id} ({$entry->title}): Craft could not save the entry.\n";
                    continue;
                }

                $migrated++;
                echo "Migrated entry {$entry->id} ({$entry->title}).\n";
            } catch (Throwable $e) {
                $failed[] = $entry->id;
                $exceptionClass = $e::class;
                $message = $this->sanitizeExceptionMessage($e);
                Craft::error("Address migration failed for entry {$entry->id} ({$exceptionClass}): {$message}", __METHOD__);
                echo "Failed entry {$entry->id} ({$entry->title}): {$exceptionClass}. See the Craft logs.\n";
            }
        }

        echo "Address migration complete: {$migrated} migrated, {$skipped} already populated, {$missing} without a legacy address, ".count($failed)." failed.\n";

        if ($failed) {
            echo 'Failed entry IDs: '.implode(', ', array_unique($failed))."\n";
            return false;
        }

        return true;
    }

    public function down(bool $throwExceptions = false): bool
    {
        echo "This migration preserves businessAddress data and cannot be reverted safely.\n";
        return false;
    }

    private function addressQuery(object $address): string
    {
        $region = trim(implode(' ', array_filter([
            $address->administrativeArea,
            $address->postalCode,
        ])));
        $cityRegion = trim((string)$address->locality);

        if ($cityRegion && $region) {
            $cityRegion .= ", {$region}";
        } elseif ($region) {
            $cityRegion = $region;
        }

        return implode(', ', array_filter([
            $address->addressLine1,
            $address->addressLine2,
            $cityRegion,
            $address->countryCode,
        ]));
    }

    private function sameCountry(?string $source, ?string $result): bool
    {
        return !$source || !$result || strtoupper($source) === strtoupper($result);
    }

    private function sanitizeExceptionMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        foreach ([GoogleMaps::getServerKey(), GoogleMaps::getBrowserKey()] as $key) {
            if ($key) {
                $message = str_replace([$key, rawurlencode($key)], '[redacted]', $message);
            }
        }

        return $message;
    }
}
