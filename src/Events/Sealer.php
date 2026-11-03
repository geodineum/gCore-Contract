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
 */
class Sealer
{
    public const PUB = '/etc/geodineum/store-seal.pub';
    public const BIN = '/opt/geodineum/Geodineum-STORE/target/release/geodineum-seal';

    /** @var string */
    private $bin;
    /** @var string */
    private $pub;

    public function __construct(string $bin, string $pub)
    {
        $this->bin = $bin;
        $this->pub = $pub;
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

    public function seal(string $plain, string $context): string
    {
        if (!is_executable($this->bin)) {
            throw new \RuntimeException("seal: {$this->bin} is not executable (sealing is on: STORE's key is installed)");
        }
        $p = proc_open([$this->bin, 'seal', '--pub', $this->pub, '--aad', $context],
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
