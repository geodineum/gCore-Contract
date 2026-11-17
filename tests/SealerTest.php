<?php
declare(strict_types=1);

// php tests/SealerTest.php
require __DIR__ . '/../src/Events/Sealer.php';

use gCore\Contracts\Events\Sealer;

$pass = 0;
$fail = 0;
$ok = static function (string $what, bool $cond) use (&$pass, &$fail): void {
    $cond ? $pass++ : $fail++;
    echo ($cond ? '  ok   ' : '  FAIL ') . $what . "\n";
};
$threw = static function (callable $fn, string $needle): bool {
    try {
        $fn();
    } catch (\RuntimeException $e) {
        return strpos($e->getMessage(), $needle) !== false;
    }
    return false;
};

$file = ['mode' => 0100750, 'uid' => 0];
$dir = ['mode' => 040755, 'uid' => 0];
$ok('a root-owned tool in a root-owned directory may run', Sealer::untrusted($file, $dir) === null);
$ok('a tool owned by someone else may not', Sealer::untrusted(['uid' => 33] + $file, $dir) === 'is not owned by root');
$ok('a group-writable tool may not', Sealer::untrusted(['mode' => 0100770] + $file, $dir) === 'is writable by group or others');
$ok('a world-writable tool may not', Sealer::untrusted(['mode' => 0100757] + $file, $dir) === 'is writable by group or others');
$ok('a tool in a directory others own may not', Sealer::untrusted($file, ['uid' => 1000] + $dir) === 'sits in a directory not owned by root');
$ok('a tool in a group-writable directory may not (a build tree)', Sealer::untrusted($file, ['mode' => 042775] + $dir) === 'sits in a directory writable by group or others');
$ok('nor in a sticky world-writable one', Sealer::untrusted($file, ['mode' => 041777] + $dir) === 'sits in a directory writable by group or others');
$ok('a directory is not a tool', Sealer::untrusted(['mode' => 040755] + $file, $dir) === 'is not a regular file');
$ok('nothing to inspect refuses', Sealer::untrusted(false, $dir) === 'cannot be inspected');

$ok('the default tool is outside any build tree', strpos(Sealer::BIN, '/target/') === false && strpos(Sealer::BIN, '/opt/') !== 0);

$tmp = sys_get_temp_dir() . '/sealer-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700);
file_put_contents("$tmp/seal", "#!/bin/sh\ncat >/dev/null\nprintf 'gs1:stand-in'\n");
chmod("$tmp/seal", 0700);
$mine = function_exists('posix_geteuid') ? posix_geteuid() : (int)getmyuid();

$ok('a tool this user owns is refused for root\'s sealer, before it runs', $threw(static function () use ($tmp): void {
    (new Sealer("$tmp/seal", "$tmp/k.pub"))->seal('x', 'ctx');
}, 'is not owned by root'));
$ok('a missing tool is refused', $threw(static function () use ($tmp): void {
    (new Sealer("$tmp/none", "$tmp/k.pub"))->seal('x', 'ctx');
}, 'does not exist'));
$ok('the same tool, trusted for its owner, seals', (new Sealer("$tmp/seal", "$tmp/k.pub", $mine))->seal('x', 'ctx') === 'gs1:stand-in');
chmod("$tmp/seal", 0770);
$ok('and is refused once its group may write it', $threw(static function () use ($tmp, $mine): void {
    (new Sealer("$tmp/seal", "$tmp/k.pub", $mine))->seal('x', 'ctx');
}, 'writable by group or others'));
chmod("$tmp/seal", 0700);
chmod($tmp, 0770);
$ok('or once its directory is group-writable', $threw(static function () use ($tmp, $mine): void {
    (new Sealer("$tmp/seal", "$tmp/k.pub", $mine))->seal('x', 'ctx');
}, 'directory writable by group or others'));
chmod($tmp, 0700);
symlink("$tmp/seal", "$tmp/link");
$ok('a link is judged by what it points to', (new Sealer("$tmp/link", "$tmp/k.pub", $mine))->seal('x', 'ctx') === 'gs1:stand-in');

if (is_file('/bin/true') && fileowner(realpath('/bin/true')) === 0) {
    $ok('a root-owned system tool passes the check and runs (it just seals nothing)', $threw(static function () use ($tmp): void {
        (new Sealer('/bin/true', "$tmp/k.pub"))->seal('x', 'ctx');
    }, 'seal: exit 0'));
}

putenv("GEODINEUM_SEAL_PUB={$tmp}/k.pub");
file_put_contents("$tmp/k.pub", 'stand-in');
putenv("GEODINEUM_SEAL_BIN={$tmp}/seal");
$ok('the environment\'s tool gets the same check (root only)', $threw(static function (): void {
    Sealer::fromEnvironment()->seal('x', 'ctx');
}, 'is not owned by root'));
putenv('GEODINEUM_SEAL_BIN');
putenv('GEODINEUM_SEAL_PUB');

array_map('unlink', glob("$tmp/*"));
rmdir($tmp);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
