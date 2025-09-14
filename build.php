<?php

use GuzzleHttp\Client;

include __DIR__ . "/vendor/autoload.php";

$srcvers = "17.0";
$baseurl = "https://mirror.freepbx.org";
$xmlsrc = "$baseurl/all-" . $srcvers . ".xml";
$stagingdir = __DIR__ . "/staging/$srcvers";
$buildver = "2509.03-1";
$buildroot = "/usr/local/data/quadpbx-deb/quadpbx-$buildver";

$pkgdest = "$buildroot/opt/quadpbx/modules";
$webdest = "/var/www/html/quadpbx";


if (!is_dir($stagingdir)) {
    mkdir($stagingdir, 0777, true);
}
if (is_dir($buildroot)) {
    system("rm -rf $buildroot");
}
mkdir($buildroot, 0777, true);
createControlFile($buildroot, $buildver);

$outfile = "/tmp/foo.xml";
if (!file_exists($outfile)) {
    $c = new Client();
    $req = $c->get($xmlsrc);
    file_put_contents($outfile, $req->getBody());
    print "loaded\n";
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
processPackage($fw, $pkgdest, "framework", $webdest, "amp_conf/htdocs");
unset($used['framework']);
foreach ($used as $name => $x) {
    processPackage($x, $pkgdest, $name);
}
print "Now do this to build the deb from $buildroot\n";
print "dpkg -b $buildroot /usr/local/repo/repo-tools/incoming\n";
exit;

function processPackage(SimpleXMLElement $m, string $pkgdest, string $name, ?string $linkdest = null, ?string $subdest = null)
{
    global $buildroot;
    $moddir = $pkgdest . "/$name";
    mkdir($moddir, 0777, true);
    $vstr = getXmlModVersionAsString($m);
    $destdir = "$moddir/$vstr/";
    print "Now thinking about $name going to $destdir\n";
    extractTarball($m->destfile, $destdir, [$name]);
    // $buildroot/opt/quadpbx/modules/$name
    chdir($moddir);
    symlink("./$vstr", "./current");
    if ($linkdest) {
        $parent = dirname(str_replace("//", "/", "$buildroot/$linkdest"));
        print "I want to go to $parent\n";
        mkdir($parent, 0777, true);
        chdir($parent);
        $rel = "../../../opt/quadpbx/modules/$name/current";
        if ($subdest) {
            $rel .= "/$subdest";
        }
        $sname = basename($linkdest);
        symlink($rel, $sname);
        system("ls -al $rel");
    }
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
    $output = str_replace(["__VERSION__", "__PHPVER__"], [$buildver, "8.3"], $control);
    file_put_contents($debdir . "/control", $output);
}

function getXmlModVersionAsString(SimpleXMLElement $x): string
{
    $v = (string) $x->version;
    return $v;
}
