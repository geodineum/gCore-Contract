<?php
declare(strict_types=1);

namespace gCore\Contracts\Events;

/**
 * Rebuild a module's state from its events alone (the local stream, or what STORE hands back).
 * Documents come from each event's state; indexes and lookup maps are derived by the module's
 * own rules, passed in. Sealed personal fields are not in an event's `p`: a rebuild from the
 * stream restores documents without them, and the email maps they derive come back only from
 * STORE (which can open them).
 */
final class Replay
{
    /**
     * @param object $kv set/delete/hSet
     * @param array $events oldest first: {t, id, v, p}
     * @param callable(string $type, string $id, mixed $doc): array{0:?string,1:string} $indexOf
     * @param array<string,string> $emailMaps type => map name, for documents that carry an email
     * @return array{applied:int, skipped:int}
     */
    public static function rebuild(object $kv, string $ns, string $site, array $events, callable $indexOf, array $emailMaps = []): array
    {
        $latest = [];
        $quoted = preg_quote($ns, '/');
        foreach ($events as $e) {
            if (!preg_match('/\A' . $quoted . '\.([a-z_]+)\.([a-z_]+)\z/', (string)($e['t'] ?? ''), $m)) {
                continue;
            }
            $key = $m[1] . '|' . (string)$e['id'];
            if (!isset($latest[$key]) || (int)$e['v'] >= (int)$latest[$key]['v']) {
                $latest[$key] = ['type' => $m[1], 'verb' => $m[2], 'id' => (string)$e['id'], 'v' => (int)$e['v'], 'p' => (string)$e['p'],
                    'eh' => preg_match('/\A[0-9a-f]{64}\z/', (string)($e['eh'] ?? '')) ? (string)$e['eh'] : null];
            }
        }
        ksort($latest, SORT_STRING);
        $applied = 0;
        $skipped = 0;
        foreach ($latest as $x) {
            $docKey = EventStore::keyFor($ns, $site, $x['type'], $x['id']);
            $doc = json_decode($x['p'], true);
            [$index, $member] = $indexOf($x['type'], $x['id'], $doc);
            $map = $emailMaps[$x['type']] ?? null;
            // the email itself, or its hash when the event's personal fields were sealed
            $eh = is_array($doc) && isset($doc['email']) ? hash('sha256', (string)$doc['email']) : $x['eh'];
            if ($x['verb'] === 'deleted') {
                $kv->delete($docKey);
                if ($map !== null && $eh !== null) {
                    $kv->hSet(EventStore::keyFor($ns, $site, 'map', $map), $eh, '');
                }
                if ($index !== null) {
                    $kv->hSet(EventStore::keyFor($ns, $site, 'ix', $index), $member, '');
                }
                $applied++;
                continue;
            }
            if (!is_array($doc)) {
                $skipped++;
                continue;
            }
            $kv->set($docKey, $x['p']);
            if ($index !== null) {
                $kv->hSet(EventStore::keyFor($ns, $site, 'ix', $index), $member, '1');
            }
            if ($map !== null && $eh !== null) {
                $kv->hSet(EventStore::keyFor($ns, $site, 'map', $map), $eh, $x['id']);
            }
            $applied++;
        }
        return ['applied' => $applied, 'skipped' => $skipped];
    }
}
