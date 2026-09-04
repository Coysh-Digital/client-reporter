<?php

declare(strict_types=1);

namespace App\Support;

use App\Integrations\Support\DiscoveredConnection;
use App\Models\Client;
use Illuminate\Support\Collection;

/**
 * Matches provider entities (billing/accounting contacts) to clients, for the
 * workspace connect flow. Prefers matching by contact email — the most
 * reliable signal — and falls back to an exact (case-insensitive) name match
 * against the client's name.
 */
class ClientMatcher
{
    /**
     * Propose a client id for each discovered entity (by array index), or null
     * when nothing matches.
     *
     * @param  array<int, DiscoveredConnection>  $discovered
     * @param  Collection<int, Client>  $clients
     * @return array<int, int|null>
     */
    public static function match(array $discovered, Collection $clients): array
    {
        $byEmail = [];
        $byName = [];
        foreach ($clients as $client) {
            if ($client->contact_email && ! isset($byEmail[strtolower(trim($client->contact_email))])) {
                $byEmail[strtolower(trim($client->contact_email))] = $client->id;
            }
            $name = strtolower(trim($client->name));
            if ($name !== '' && ! isset($byName[$name])) {
                $byName[$name] = $client->id;
            }
        }

        $out = [];
        foreach ($discovered as $index => $entity) {
            $email = $entity->email !== null ? strtolower(trim($entity->email)) : null;
            $label = strtolower(trim($entity->label));

            $out[$index] = ($email !== null ? ($byEmail[$email] ?? null) : null) ?? ($byName[$label] ?? null);
        }

        return $out;
    }
}
