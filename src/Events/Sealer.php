<?php
declare(strict_types=1);

namespace gCore\Contracts\Events;

/**
 * Seals a value to the STORE node's public key (gs1: X25519 + ML-KEM-768, AES-256-GCM) with
 * the geodineum-seal tool, so personal data leaves this process only sealed. The plain value
 * goes over a pipe, never on a command line. Only the STORE node can open it.
 *
 * A node seals when STORE's public key is installed (geodineum store seal-key); then a seal
 * that fails refuses the write (an exception), it never falls back to clear.
 *
 * The tool runs with the site's identity and sees every sealed value in clear, so it is run
 * only when root owns it and its directory and neither is writable by group or others.
 */
class Sealer
{
    public const PUB = '/etc/geodineum/store-seal.pub';
    public const BIN = '/usr/local/lib/geodineum/geodineum-seal';

    /** @var string */
    private $bin;
    /** @var string */
    private $pub;
    /** @var int */
    private $owner;

    /** $owner: the uid the tool and its directory must belong to (root; another only in a test). */
    public function __construct(string $bin, string $pub, int $owner = 0)
    {
        $this->bin = $bin;
        $this->pub = $pub;
        $this->owner = $owner;
    }

    /** The node's sealer, or null when no STORE key is installed here (sealing off). */
    public static function fromEnvironment(): ?self
    {
        $pub = getenv('GEODINEUM_SEAL_PUB') ?: self::PUB;
        if (!@is_readable($pub)) {
            return null;
        }
        return new self(getenv('GEODINEUM_SEAL_BIN') ?: self::BIN, $pub);
    }

    /** The associated data of an event's sealed fields: where the value lives. */
    public static function context(string $stream, string $kind, string $id, int $version): string
    {
        return "gs1|{$stream}|{$kind}|{$id}|{$version}";
    }

    /**
     * Why a file and its directory, as stat() gives them, are not safe to run, or null.
     *
     * @param array|false $file
     * @param array|false $dir
     */
    public static function untrusted($file, $dir, int $owner = 0): ?string
    {
        if (!is_array($file) || !is_array($dir)) {
            return 'cannot be inspected';
        }
        if (($file['mode'] & 0170000) !== 0100000) {
            return 'is not a regular file';
        }
        if ((int)$file['uid'] !== $owner) {
            return 'is not owned by ' . ($owner === 0 ? 'root' : "uid {$owner}");
        }
        if (($file['mode'] & 0022) !== 0) {
            return 'is writable by group or others';
        }
        if (($file['mode'] & 0111) === 0) {
            return 'is not executable';
        }
        if ((int)$dir['uid'] !== $owner) {
            return 'sits in a directory not owned by ' . ($owner === 0 ? 'root' : "uid {$owner}");
        }
        if (($dir['mode'] & 0022) !== 0) {
            return 'sits in a directory writable by group or others';
        }
        return null;
    }

    /** The tool's real path once it passes the ownership check; an exception names why it does not. */
    public function trustedBin(): string
    {
        clearstatcache();
        $real = @realpath($this->bin);
        $why = $real === false ? 'does not exist' : self::untrusted(@stat($real), @stat(dirname($real)), $this->owner);
        if ($why !== null) {
            throw new \RuntimeException("seal: {$this->bin} {$why}; refusing to run it (sealing is on: STORE's key is installed)");
        }
        return $real;
    }

    public function seal(string $plain, string $context): string
    {
        $bin = $this->trustedBin();
        $p = proc_open([$bin, 'seal', '--pub', $this->pub, '--aad', $context],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) {
            throw new \RuntimeException('seal: could not start geodineum-seal');
        }
        fwrite($pipes[0], $plain);
        fclose($pipes[0]);
        $out = (string)stream_get_contents($pipes[1]);
        $err = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $rc = proc_close($p);
        if ($rc !== 0 || strncmp($out, 'gs1:', 4) !== 0) {
            throw new \RuntimeException('seal: ' . ($err !== '' ? $err : "exit {$rc}"));
        }
        return $out;
    }
}
