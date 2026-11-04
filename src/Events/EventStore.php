<?php
declare(strict_types=1);

namespace gCore\Contracts\Events;

/**
 * A module's one door to ValKey: documents, their events, leases, indexes and lookup maps,
 * under {site}:<ns>:… (Planning's Store, shared: planning, shop).
 *
 * Every entity is one JSON document under {site}:<ns>:<type>:<id>. A write is serialised per
 * entity by a lease (GNODE_LOCK_ACQUIRE: the adapter has no WATCH/MULTI and sites may not EVAL)
 * and is preceded by its event on {site}:<ns>:events with pz=1, which Geodineum-STORE keeps.
 * The event comes first: if the process dies between the two, the state lags its history and a
 * replay repairs it.
 *
 * Personal fields (the `sensitive` names) are never written to the event in clear when the node
 * seals: they travel as `sc`, sealed to STORE's key, and the event names its subject in `sj`
 * (an id, never an address) so an erasure finds it. The live document keeps them: it is the
 * working copy the module runs on.
 */
class EventStore
{
    protected const LEASE_SECONDS = 5;
    protected const LEASE_TRIES = 40;

    /** @var object */
    protected $kv;
    /** @var object nowMs(): int */
    protected $clock;
    /** @var string */
    protected $ns;
    /** @var callable(string $reason, string $message): \Throwable */
    protected $fail;
    /** @var Sealer|null */
    protected $sealer;

    public function __construct(object $kv, object $clock, string $ns, ?callable $fail = null, ?Sealer $sealer = null)
    {
        if (!preg_match('/\A[a-z][a-z0-9_]{0,31}\z/', $ns)) {
            throw new \InvalidArgumentException("event store namespace {$ns}");
        }
        $this->fail = $fail ?? static function (string $reason, string $message): \Throwable {
            return new \RuntimeException("{$reason}: {$message}");
        };
        foreach (['get', 'set', 'delete', 'hSet', 'hGetAll', 'xAdd', 'xRange', 'fcall'] as $m) {
            if (!method_exists($kv, $m)) {
                throw ($this->fail)('unavailable', "storage adapter lacks {$m}()");
            }
        }
        $this->kv = $kv;
        $this->clock = $clock;
        $this->ns = $ns;
        $this->sealer = $sealer;
    }

    public static function keyFor(string $ns, string $site, string ...$parts): string
    {
        return '{' . $site . '}:' . $ns . ':' . implode(':', $parts);
    }

    protected function k(string $site, string ...$parts): string
    {
        return self::keyFor($this->ns, $site, ...$parts);
    }

    public function ns(): string { return $this->ns; }

    public function sealing(): bool { return $this->sealer !== null; }

    public function load(string $site, string $type, string $id): ?array
    {
        $raw = $this->kv->get($this->k($site, $type, $id));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $doc = json_decode($raw, true);
        return is_array($doc) ? $doc : null;
    }

    /** @return array<string,array> id => document, for the ids in an index */
    public function loadAll(string $site, string $type, string $index): array
    {
        $out = [];
        foreach ($this->members($site, $index) as $id) {
            $doc = $this->load($site, $type, $id);
            if ($doc !== null) {
                $out[$id] = $doc;
            }
        }
        return $out;
    }

    /**
     * Read-modify-write one entity under its lease. $fn gets the current document (null if
     * none) and returns the new one, or null to leave it unchanged. The event
     * (kind = <ns>.<type>.<verb>) is written before the state.
     */
    public function mutate(string $site, string $type, string $id, string $verb, array $actor, callable $fn, array $sensitive = []): array
    {
        $key = $this->k($site, $type, $id);
        $token = bin2hex(random_bytes(12));
        $this->lease($site, $key, $token);
        try {
            $current = $this->load($site, $type, $id);
            $next = $fn($current);
            if ($next === null) {
                if ($current === null) {
                    throw ($this->fail)('notFound', "{$type} {$id}");
                }
                return $current;
            }
            $next['id'] = $id;
            $next['v'] = (int)($current['v'] ?? 0) + 1;
            $next['updated_ms'] = $this->clock->nowMs();
            if (!isset($next['created_ms'])) {
                $next['created_ms'] = $current['created_ms'] ?? $next['updated_ms'];
            }
            $this->event($site, "{$this->ns}.{$type}.{$verb}", $id, $next['v'], $actor, $next, $sensitive);
            $this->kv->set($key, (string)json_encode($next, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return $next;
        } finally {
            $this->release($site, $key, $token);
        }
    }

    /** Remove an entity; its deletion is an event too. */
    public function remove(string $site, string $type, string $id, array $actor, array $sensitive = ['email', 'name', 'rules']): bool
    {
        $key = $this->k($site, $type, $id);
        $token = bin2hex(random_bytes(12));
        $this->lease($site, $key, $token);
        try {
            $current = $this->load($site, $type, $id);
            if ($current === null) {
                return false;
            }
            // The last state rides along, so a replay can undo what the record derived (an email map entry).
            $marked = array_values(array_intersect($sensitive, array_keys($current)));
            $this->event($site, "{$this->ns}.{$type}.deleted", $id, (int)($current['v'] ?? 0) + 1, $actor, $current, $marked);
            return $this->kv->delete($key);
        } finally {
            $this->release($site, $key, $token);
        }
    }

    /** Index membership: a hash of id => "1"; removal writes "" (the adapter has no HDEL). */
    public function index(string $site, string $index, string $id, bool $member = true): void
    {
        $this->kv->hSet($this->k($site, 'ix', $index), $id, $member ? '1' : '');
    }

    /** @return string[] */
    public function members(string $site, string $index): array
    {
        $all = $this->kv->hGetAll($this->k($site, 'ix', $index));
        $ids = [];
        foreach (is_array($all) ? $all : [] as $id => $flag) {
            if ($flag === '1') {
                $ids[] = (string)$id;
            }
        }
        sort($ids, SORT_STRING);
        return $ids;
    }

    /** A lookup map (e.g. email hash -> agent id); "" means removed. */
    public function mapPut(string $site, string $map, string $field, string $value): void
    {
        $this->kv->hSet($this->k($site, 'map', $map), $field, $value);
    }

    public function mapGet(string $site, string $map, string $field): ?string
    {
        $all = $this->kv->hGetAll($this->k($site, 'map', $map));
        $v = is_array($all) ? ($all[$field] ?? '') : '';
        return $v === '' ? null : (string)$v;
    }

    /** A plain value with an expiry (offers, ticks); not an entity, not an event. */
    public function put(string $key, string $value, ?int $ttlSeconds = null): void
    {
        $this->kv->set($key, $value, $ttlSeconds);
    }

    public function get(string $key): ?string
    {
        $v = $this->kv->get($key);
        return is_string($v) ? $v : null;
    }

    public function drop(string $key): bool { return $this->kv->delete($key); }

    /**
     * Run $fn while holding a lease on $resource (waits briefly, then refuses as busy).
     *
     * @return mixed what $fn returns
     */
    public function withLease(string $site, string $resource, callable $fn)
    {
        $token = bin2hex(random_bytes(12));
        $this->lease($site, $resource, $token);
        try {
            return $fn();
        } finally {
            $this->release($site, $resource, $token);
        }
    }

    /** Exactly one caller wins a resource until the lease expires or is released. */
    /** True when taken, false when another holds it; a lock call that errors is `unavailable`, never "busy". */
    public function tryLease(string $site, string $resource, string $token, int $ttlSeconds): bool
    {
        try {
            $r = $this->kv->fcall('GNODE_LOCK_ACQUIRE', [$resource], [$site, $token, $ttlSeconds]);
        } catch (\Throwable $e) {
            throw ($this->fail)('unavailable', 'the lock could not be taken: ' . $e->getMessage());
        }
        if (!is_int($r) && !(is_string($r) && ctype_digit($r))) {
            throw ($this->fail)('unavailable', 'the lock could not be taken (the lock function answered ' . var_export($r, true) . ')');
        }
        return (int)$r === 1;
    }

    public function releaseLease(string $site, string $resource, string $token): bool
    {
        return (int)$this->kv->fcall('GNODE_LOCK_RELEASE', [$resource], [$site, $token]) === 1;
    }

    /** @return array<int,array> events of this site, oldest first */
    public function events(string $site, string $from = '-', string $to = '+', ?int $count = null): array
    {
        $out = [];
        foreach ($this->kv->xRange($this->k($site, 'events'), $from, $to, $count) as $id => $fields) {
            $fields['_id'] = (string)$id;
            $out[] = $fields;
        }
        return $out;
    }

    public function event(string $site, string $kind, string $id, int $version, array $actor, array $state, array $sensitive): void
    {
        $stream = $this->k($site, 'events');
        $fields = ['pz' => '1', 't' => $kind, 'id' => $id, 'v' => (string)$version, 'by' => self::actorTag($actor),
                   'ts' => (string)$this->clock->nowMs()];
        $secret = array_intersect_key($state, array_flip($sensitive));
        if ($secret !== []) {
            $fields['sx'] = implode(',', array_keys($secret));
            // The person the personal fields belong to (an id): `subject`, `customer` or `agent` when the
            // document names one, else the document itself.
            $who = $state['subject'] ?? $state['customer'] ?? $state['agent'] ?? $id;
            $fields['sj'] = is_string($who) && $who !== '' ? $who : $id;
            if ($this->sealer !== null) {
                try {
                    $fields['sc'] = $this->sealer->seal((string)json_encode($secret, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        Sealer::context($stream, $kind, $id, $version));
                } catch (\Throwable $e) {
                    // Never in clear instead: the write is refused, nothing of it is kept.
                    throw ($this->fail)('unavailable', 'personal data could not be sealed (' . $e->getMessage() . ')');
                }
                $state = array_diff_key($state, $secret);
            }
        }
        $fields['p'] = (string)json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->kv->xAdd($stream, '*', $fields);
    }

    public static function actorTag(array $actor): string
    {
        $kind = (string)($actor['kind'] ?? 'system');
        $id = (string)($actor['id'] ?? '');
        return $id === '' ? $kind : $kind . ':' . $id;
    }

    protected function lease(string $site, string $key, string $token): void
    {
        for ($i = 0; $i < static::LEASE_TRIES; $i++) {
            if ($this->tryLease($site, $key, $token, static::LEASE_SECONDS)) {
                return;
            }
            usleep(25000);
        }
        throw ($this->fail)('conflict', 'busy: another change to the same record is in progress');
    }

    protected function release(string $site, string $key, string $token): void
    {
        try {
            $this->releaseLease($site, $key, $token);
        } catch (\Throwable $e) {
            // the lease expires on its own
        }
    }
}
