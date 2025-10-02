#!/usr/bin/env php
<?php

use GuzzleHttp\Client;

include __DIR__ . "/vendor/autoload.php";

$srcvers = "17.0";
$baseurl = "https://mirror.freepbx.org";
$xmlsrc = "$baseurl/all-" . $srcvers . ".xml";
$stagingdir = __DIR__ . "/staging/$srcvers";
$buildver = "2509.06-1";
$buildroot = "/usr/local/data/quadpbx-deb/quadpbx-$buildver";

$pkgdest = "$buildroot/opt/quadpbx/modules";
$patchdest = "$buildroot/opt/quadpbx/patches";
$webdest = "/var/www/html/quadpbx";

// Auto-push when being built by xrobau
$repo = false;

// Used when testing builds
$devtest = true;

$knownpatches = glob(__DIR__ . "/patches/*.patch");
$patchscripts = glob(__DIR__ . "/patches/*.sh");
$debscripts = glob(__DIR__ . "/scripts/*");


if (!is_dir($stagingdir)) {
    mkdir($stagingdir, 0777, true);
}
if (is_dir($buildroot)) {
    system("rm -rf $buildroot");
}
mkdir($buildroot, 0777, true);
createControlFile($buildroot, $buildver);
foreach ($debscripts as $s) {
    $dest = "$buildroot/DEBIAN/" . basename($s);
    copy($s, $dest);
    chmod($dest, 0755);
}

$outfile = "/tmp/foo.xml";
if (!file_exists($outfile)) {
    $c = new Client();
    $req = $c->get($xmlsrc);
    file_put_contents($outfile, $req->getBody());
    print "loaded /tmp/foo from mirror, restart and try again\n";
    exit;
}

$x = simplexml_load_file($outfile);
$modules = $x->module[0];

$used = [];
foreach ($modules as $name => $element) {
    if ($element->license == 'Commercial') {
        print "Skipping $name as it's commercial\n";
    } else {
        $used[$name] = $element;
    }
}
print "Found " . count($used) . " modules\n";
foreach ($used as $name => $x) {
    downloadModule($x);
}

print "Starting with framework\n";
$fw = $used['framework'];
$fwdir = processPackage($fw, $pkgdest, "framework", true);
$moddests = str_replace("//", "/", "$fwdir/amp_conf/htdocs/admin/modules/");
mkdir($moddests, 0777, true);
unset($used['framework']);
foreach ($used as $name => $x) {
    $pdir = processPackage($x, $pkgdest, $name);
    chdir($moddests);
    $modsrc = "../../../../../../$name/" . basename($pdir);
    symlink($modsrc, $name);
}

mkdir($patchdest, 0777, true);

chdir($buildroot);
foreach ($knownpatches as $src) {
    $fn = basename($src);
    $dest = "$patchdest/$fn";
    copy($src, $dest);
    $cmd = "patch -p0 < $dest";
    print "$cmd\n";
    system($cmd);
}

foreach ($patchscripts as $cmd) {
    print "Running $cmd\n";
    putenv("THISSCRIPT=$cmd");
    $rescode = 0;
    system($cmd, $rescode);
    if ($rescode !== 0) {
        exit($rescode);
    }
}

// This should probably be a recursive directory iterator
$filesdir = __DIR__ . '/files/';
$cmd = "rsync -av $filesdir $buildroot/";
system($cmd);

$hooks = glob(__DIR__ . "/hooks/*");
chdir($buildroot);
foreach ($hooks as $h) {
    print "Running $h in $buildroot\n";
    system($h);
}

if ($repo) {
    $cmd = "dpkg -b $buildroot /usr/local/repo/repo-tools/incoming; cd /usr/local/repo/repo-tools; make repo";
    print "Now building using:\n  $cmd\n";
    system($cmd);
} else {
    $outdir = "/tmp";
    $cmd = "dpkg -b $buildroot $outdir";
    $inscmd = "dpkg -i $outdir/quadpbx-og_" . $buildver . "_all.deb";
    if ($devtest) {
        print "Building using $cmd\n";
        system($cmd);
        print "Now install the deb using $inscmd\n";
        exit;
    }
    print "Now do this to build the deb from $buildroot\n$cmd; dpkg -i /tmp/quadpbx-og_" . $buildver . "_all.deb\n";
}

function processPackage(SimpleXMLElement $m, string $pkgdest, string $name, bool $linkcurrent = false): string
{
    global $buildroot;
    $moddir = $pkgdest . "/$name";
    mkdir($moddir, 0777, true);
    $vstr = getXmlModVersionAsString($m);
    $destdir = "$moddir/$vstr/";
    print "Now thinking about $name going to $destdir\n";
    extractTarball($m->destfile, $destdir, [$name]);
    if ($linkcurrent) {
        chdir($moddir);
        symlink("./$vstr", "./current");
    }
    return $destdir;
}

function downloadModule(\SimpleXMLElement $x)
{
    global $baseurl, $stagingdir;
    $rawfile = preg_replace('/.gpg$/', '', $x->location);
    $srcurl = $baseurl . "/mirror/" . $rawfile;
    $filename = basename($srcurl);
    $destfile = $stagingdir . "/$filename";
    if (!file_exists($destfile)) {
        print "I want to download it from $srcurl to $destfile\n";
        $c = new Client();
        $req = $c->request('GET', $srcurl, ['sink' => $destfile]);
        print "Response code: " . $req->getStatusCode() . " and " . json_encode($req->getHeaders()) . "\n";
    } else {
        print "$destfile exists\n";
    }
    $x->destfile = $destfile;
}

function extractTarball(string $filename, string $destdir, array $strips = [])
{
    global $buildroot;
    $tmpdir = $buildroot . "/__tmp";
    if (is_dir($tmpdir)) {
        system("rm -rf $tmpdir");
    }
    mkdir($tmpdir, 0777, true);
    mkdir($destdir, 0777, true);
    $phar = new PharData($filename);
    $phar->extractTo($tmpdir);
    chdir($tmpdir);
    foreach ($strips as $d) {
        if (!is_dir($d)) {
            $dir = getcwd();
            print "I was asked to strip $d from $filename but it does not exist. I am in $dir\n";
            system("ls -al");
            exit;
        }
        chdir($d);
    }
    moveContents(getcwd(), $destdir);
    system("rm -rf $tmpdir");
}

function moveContents(string $srcdir, string $destdir)
{
    foreach (new DirectoryIterator($srcdir) as $fileInfo) {
        if ($fileInfo->isDot()) continue;
        $fullpath = $fileInfo->getPath() . "/" . $fileInfo->getFilename();
        $destpath = $destdir . "/" . $fileInfo->getFilename();
        // print "Moving $fullpath to $destpath\n";
        rename($fullpath, $destpath);
    }
}

function createControlFile(string $buildroot, string $buildver)
{
    $debdir = $buildroot . "/DEBIAN";
    mkdir($debdir, 0777, true);
    $control = file_get_contents(__DIR__ . "/control");
    $output = str_replace(["__VERSION__", "__PHPVER__"], [$buildver, "8.4"], $control);
    file_put_contents($debdir . "/control", $output);
}

function getXmlModVersionAsString(SimpleXMLElement $x): string
{
    $v = (string) $x->version;
    return $v;
}
