<?php
/**
 * AI Infos File Manager — a tiny, single-file PHP file manager.
 * Drop this file on your server, set a password below, open it in the browser.
 *
 * Features: login, browse, breadcrumbs, search filter, upload (multi + drag & drop),
 * new file/folder, rename, delete (single + bulk), download, image/PDF preview,
 * in-browser text editor (Ctrl+S to save). Locked to FM_ROOT — can't escape it.
 */

// ===================== CONFIG =====================
// Plain text password, OR (better) a hash generated with:
//   php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
$FM_PASSWORD = 'change-me';
// Folder to manage. __DIR__ = folder this file is in. Or use $_SERVER['DOCUMENT_ROOT'].
$FM_ROOT     = __DIR__;
$FM_TITLE    = 'AI Infos File Manager';
$FM_EDIT_MAX = 2 * 1024 * 1024; // max file size (bytes) editable in the browser
// ==================================================

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

session_name('ofm_sess');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
}
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$SELF = $_SERVER['SCRIPT_NAME'];
$ROOT = realpath($FM_ROOT);
$THIS = realpath(__FILE__);

// ---------- helpers ----------
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rel_clean($r) { return trim(str_replace('\\', '/', (string)$r), '/'); }
function abs_path($rel) {
    global $ROOT;
    $rel = rel_clean($rel);
    $full = realpath($rel === '' ? $ROOT : $ROOT . '/' . $rel);
    if ($full === false) return false;
    if ($full !== $ROOT && strpos($full, $ROOT . DIRECTORY_SEPARATOR) !== 0) return false;
    return $full;
}
function rel_of($abs) {
    global $ROOT;
    return ltrim(str_replace('\\', '/', substr($abs, strlen($ROOT))), '/');
}
function valid_name($n) {
    return $n !== '' && $n !== '.' && $n !== '..' && strpbrk($n, "/\\\0") === false && strlen($n) <= 255;
}
function hsize($b) {
    $u = ['B', 'KB', 'MB', 'GB', 'TB']; $i = 0;
    while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
    return ($i ? number_format($b, 1) : $b) . ' ' . $u[$i];
}
function ext($n) { return strtolower(pathinfo($n, PATHINFO_EXTENSION)); }
function is_editable_name($n) {
    static $t = ['txt','md','php','phtml','html','htm','css','scss','js','mjs','ts','tsx','jsx','vue','json',
        'xml','yml','yaml','ini','conf','cfg','env','sql','csv','log','sh','py','rb','go','java','c','cpp',
        'h','svg','twig','tpl','htaccess','gitignore','toml','lock','txt'];
    return in_array(ext($n), $t, true) || ($n[0] === '.' && ext($n) === substr($n, 1));
}
function is_viewable_name($n) {
    return in_array(ext($n), ['jpg','jpeg','png','gif','webp','svg','bmp','ico','avif','pdf'], true);
}
function rrm($p) {
    if (is_dir($p) && !is_link($p)) {
        foreach (scandir($p) as $f) { if ($f !== '.' && $f !== '..') rrm($p . '/' . $f); }
        return @rmdir($p);
    }
    return @unlink($p);
}
function flash($type, $msg) { $_SESSION['flash'][] = [$type, $msg]; }
function back($dirRel, $extra = '') {
    global $SELF;
    header('Location: ' . $SELF . '?d=' . rawurlencode($dirRel) . $extra);
    exit;
}
function check_pw($pw) {
    global $FM_PASSWORD;
    if (preg_match('/^\$(2y|argon2)/', $FM_PASSWORD)) return password_verify($pw, $FM_PASSWORD);
    return hash_equals($FM_PASSWORD, (string)$pw);
}

// ---------- layout ----------
function head($title) {
    global $FM_TITLE; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> · <?= h($FM_TITLE) ?></title>
<style>
:root{--bg:#f6f7f9;--card:#fff;--fg:#1d2330;--mut:#6b7385;--line:#e4e7ec;--acc:#2f6fed;--dan:#d93a3a;--ok:#1f9d55;--hov:#f0f3f8}
@media (prefers-color-scheme:dark){:root{--bg:#0f1218;--card:#171b23;--fg:#e6e9ef;--mut:#8b93a5;--line:#262c38;--acc:#5b8cff;--dan:#ff6b6b;--ok:#3ecf8e;--hov:#1d222c}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
a{color:var(--acc);text-decoration:none}a:hover{text-decoration:underline}
.wrap{max-width:1100px;margin:0 auto;padding:16px}
.top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
.brand{font-weight:700;font-size:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px}
.bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:12px;border-bottom:1px solid var(--line)}
.crumbs{padding:10px 12px;border-bottom:1px solid var(--line);word-break:break-all}
.crumbs a{font-weight:600}
button,.btn{font:inherit;border:1px solid var(--line);background:var(--card);color:var(--fg);padding:6px 12px;border-radius:7px;cursor:pointer;display:inline-block}
button:hover,.btn:hover{background:var(--hov);text-decoration:none}
.pri{background:var(--acc);border-color:var(--acc);color:#fff}.pri:hover{background:var(--acc);opacity:.9}
.dan{color:var(--dan)}
input[type=text],input[type=password],input[type=search]{font:inherit;padding:6px 10px;border:1px solid var(--line);border-radius:7px;background:var(--bg);color:var(--fg)}
table{width:100%;border-collapse:collapse}
th,td{padding:8px 12px;text-align:left;border-bottom:1px solid var(--line);white-space:nowrap}
th{font-size:12px;color:var(--mut);font-weight:600;text-transform:uppercase;letter-spacing:.03em}
tr:hover td{background:var(--hov)}
td.name{white-space:normal;word-break:break-all;width:100%}
.mut{color:var(--mut)}.acts a,.acts button{margin-right:6px;padding:2px 8px;font-size:13px}
.flash{padding:10px 12px;border-radius:8px;margin-bottom:10px;border:1px solid var(--line);background:var(--card)}
.flash.ok{border-color:var(--ok);color:var(--ok)}.flash.err{border-color:var(--dan);color:var(--dan)}
#drop{display:none;position:fixed;inset:0;background:rgba(47,111,237,.15);border:3px dashed var(--acc);z-index:9;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:var(--acc)}
textarea{width:100%;height:70vh;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;padding:12px;border:0;border-top:1px solid var(--line);background:var(--card);color:var(--fg);resize:vertical;tab-size:4;border-radius:0 0 10px 10px}
.login{max-width:340px;margin:12vh auto;padding:24px}
.login input{width:100%;margin:8px 0 14px}
@media (max-width:700px){.hide-sm{display:none}th,td{padding:8px}}
</style></head><body><div class="wrap">
<?php
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $f) echo '<div class="flash ' . h($f[0]) . '">' . h($f[1]) . '</div>';
        $_SESSION['flash'] = [];
    }
}
function foot() { echo '</div></body></html>'; exit; }

// ---------- guard: default password ----------
if ($FM_PASSWORD === 'change-me') {
    head('Setup'); ?>
    <div class="card login"><b>Set a password first.</b>
    <p class="mut">Open this file, change <code>$FM_PASSWORD</code> from <code>change-me</code> to something strong, then reload.</p></div>
    <?php foot();
}
if ($ROOT === false || !is_dir($ROOT)) { head('Error'); echo '<div class="flash err">FM_ROOT does not exist.</div>'; foot(); }

// ---------- auth ----------
if (isset($_GET['logout'])) { session_destroy(); header('Location: ' . $SELF); exit; }

if (empty($_SESSION['ofm_auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (check_pw($_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['ofm_auth'] = true;
            header('Location: ' . $SELF); exit;
        }
        sleep(2); // slow down brute force
        flash('err', 'Wrong password.');
        header('Location: ' . $SELF); exit;
    }
    head('Login'); ?>
    <form method="post" class="card login">
      <div class="brand"><?= h($GLOBALS['FM_TITLE']) ?></div>
      <input type="password" name="password" placeholder="Password" autofocus required>
      <button class="pri" style="width:100%">Log in</button>
    </form>
    <?php foot();
}

// ---------- POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Bad CSRF token'); }
    $dirRel = rel_clean($_POST['dir'] ?? '');
    $dir = abs_path($dirRel);
    if (!$dir || !is_dir($dir)) { flash('err', 'Folder not found.'); back(''); }
    $dirRel = rel_of($dir);
    $do = $_POST['do'] ?? '';

    if ($do === 'upload') {
        $n = 0;
        if (!empty($_FILES['files']['name'])) {
            foreach ((array)$_FILES['files']['name'] as $i => $name) {
                $name = basename($name);
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) { flash('err', "Upload failed: $name (error {$_FILES['files']['error'][$i]})"); continue; }
                if (!valid_name($name)) { flash('err', "Bad file name: $name"); continue; }
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dir . '/' . $name)) $n++;
                else flash('err', "Could not save $name (check folder permissions).");
            }
        }
        if ($n) flash('ok', "Uploaded $n file(s).");
        back($dirRel);
    }

    if ($do === 'mkdir' || $do === 'newfile') {
        $name = trim($_POST['name'] ?? '');
        if (!valid_name($name)) { flash('err', 'Invalid name.'); back($dirRel); }
        $target = $dir . '/' . $name;
        if (file_exists($target)) { flash('err', "\"$name\" already exists."); back($dirRel); }
        $ok = $do === 'mkdir' ? @mkdir($target, 0755) : (@file_put_contents($target, '') !== false);
        $ok ? flash('ok', "Created \"$name\".") : flash('err', "Could not create \"$name\".");
        if ($ok && $do === 'newfile') { header('Location: ' . $SELF . '?edit=' . rawurlencode(rel_of(realpath($target)))); exit; }
        back($dirRel);
    }

    if ($do === 'rename') {
        $old = $_POST['item'] ?? ''; $new = trim($_POST['newname'] ?? '');
        $src = valid_name($old) ? abs_path($dirRel . '/' . $old) : false;
        if (!$src || !valid_name($new)) { flash('err', 'Invalid name.'); back($dirRel); }
        if ($src === $THIS) { flash('err', 'Rename the manager itself via FTP/SSH.'); back($dirRel); }
        if (file_exists($dir . '/' . $new)) { flash('err', "\"$new\" already exists."); back($dirRel); }
        @rename($src, $dir . '/' . $new) ? flash('ok', "Renamed to \"$new\".") : flash('err', 'Rename failed.');
        back($dirRel);
    }

    if ($do === 'delete') {
        $n = 0;
        foreach ((array)($_POST['items'] ?? []) as $name) {
            $p = valid_name($name) ? abs_path($dirRel . '/' . $name) : false;
            if (!$p || $p === $ROOT) continue;
            if ($p === $THIS) { flash('err', 'Skipped the manager file itself.'); continue; }
            rrm($p) ? $n++ : flash('err', "Could not delete \"$name\".");
        }
        if ($n) flash('ok', "Deleted $n item(s).");
        back($dirRel);
    }

    if ($do === 'save') {
        $f = abs_path($_POST['file'] ?? '');
        if (!$f || !is_file($f)) { flash('err', 'File not found.'); back($dirRel); }
        $data = str_replace("\r\n", "\n", (string)($_POST['content'] ?? ''));
        if (!empty($_POST['crlf'])) $data = str_replace("\n", "\r\n", $data);
        @file_put_contents($f, $data) !== false ? flash('ok', 'Saved ' . basename($f) . '.') : flash('err', 'Save failed (permissions?).');
        header('Location: ' . $SELF . '?edit=' . rawurlencode(rel_of($f))); exit;
    }

    back($dirRel);
}

// ---------- GET: download ----------
if (isset($_GET['dl'])) {
    $f = abs_path($_GET['dl']);
    if (!$f || !is_file($f)) { http_response_code(404); exit('Not found'); }
    $bn = basename($f);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\n", "\r"], '', $bn) . '"; filename*=UTF-8\'\'' . rawurlencode($bn));
    header('Content-Length: ' . filesize($f));
    readfile($f); exit;
}

// ---------- GET: inline view (images / pdf), sandboxed ----------
if (isset($_GET['view'])) {
    $f = abs_path($_GET['view']);
    if (!$f || !is_file($f)) { http_response_code(404); exit('Not found'); }
    $map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
            'svg'=>'image/svg+xml','bmp'=>'image/bmp','ico'=>'image/x-icon','avif'=>'image/avif','pdf'=>'application/pdf'];
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . ($map[ext($f)] ?? 'text/plain; charset=utf-8'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox');
    header('Content-Length: ' . filesize($f));
    readfile($f); exit;
}

// ---------- GET: editor ----------
if (isset($_GET['edit'])) {
    $f = abs_path($_GET['edit']);
    if (!$f || !is_file($f)) { flash('err', 'File not found.'); back(''); }
    $rel = rel_of($f); $dirRel = rel_of(dirname($f));
    if (filesize($f) > $FM_EDIT_MAX) { flash('err', 'File too large to edit in the browser.'); back($dirRel); }
    $content = file_get_contents($f);
    if (strpos($content, "\0") !== false) { flash('err', 'That looks like a binary file.'); back($dirRel); }
    $crlf = strpos($content, "\r\n") !== false;
    head('Edit ' . basename($f)); ?>
    <div class="top"><div class="brand"><?= h($GLOBALS['FM_TITLE']) ?></div><a href="?logout">Log out</a></div>
    <form method="post" class="card" id="ed">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="dir" value="<?= h($dirRel) ?>">
      <input type="hidden" name="file" value="<?= h($rel) ?>">
      <input type="hidden" name="crlf" value="<?= $crlf ? '1' : '' ?>">
      <div class="bar">
        <a class="btn" href="?d=<?= rawurlencode($dirRel) ?>">← Back</a>
        <b style="word-break:break-all">/<?= h($rel) ?></b>
        <span class="mut"><?= hsize(filesize($f)) ?><?= is_writable($f) ? '' : ' · read-only' ?></span>
        <span style="flex:1"></span>
        <a class="btn" href="?dl=<?= rawurlencode($rel) ?>">Download</a>
        <button class="pri" <?= is_writable($f) ? '' : 'disabled' ?>>Save (Ctrl+S)</button>
      </div>
      <textarea name="content" id="ta" spellcheck="false"><?= h($content) ?></textarea>
    </form>
    <script>
    const ta=document.getElementById('ta'),fm=document.getElementById('ed');let dirty=false;
    ta.addEventListener('input',()=>dirty=true);
    ta.addEventListener('keydown',e=>{if(e.key==='Tab'){e.preventDefault();const s=ta.selectionStart;ta.setRangeText('    ',s,ta.selectionEnd,'end');dirty=true;}});
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){e.preventDefault();dirty=false;fm.submit();}});
    fm.addEventListener('submit',()=>dirty=false);
    window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
    </script>
    <?php foot();
}

// ---------- GET: directory listing ----------
$dir = abs_path($_GET['d'] ?? '');
if (!$dir || !is_dir($dir)) { if (isset($_GET['d'])) flash('err', 'Folder not found.'); $dir = $ROOT; }
$dirRel = rel_of($dir);

$items = [];
foreach (@scandir($dir) ?: [] as $n) {
    if ($n === '.' || $n === '..') continue;
    $p = $dir . '/' . $n;
    $isDir = is_dir($p);
    $items[] = ['n' => $n, 'dir' => $isDir, 'size' => $isDir ? null : @filesize($p),
        'mt' => @filemtime($p), 'perm' => substr(sprintf('%o', @fileperms($p)), -4), 'link' => is_link($p)];
}
usort($items, function ($a, $b) { return $a['dir'] === $b['dir'] ? strnatcasecmp($a['n'], $b['n']) : ($a['dir'] ? -1 : 1); });

head($dirRel === '' ? 'Root' : $dirRel); ?>
<div class="top"><div class="brand"><?= h($FM_TITLE) ?></div><a href="?logout">Log out</a></div>
<div class="card">
  <div class="crumbs">
    <a href="?d=">🏠 root</a><?php
    $acc = '';
    foreach ($dirRel === '' ? [] : explode('/', $dirRel) as $part) {
        $acc = ltrim($acc . '/' . $part, '/');
        echo ' / <a href="?d=' . rawurlencode($acc) . '">' . h($part) . '</a>';
    } ?>
  </div>
  <div class="bar">
    <form method="post" enctype="multipart/form-data" id="up" style="display:contents">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="do" value="upload">
      <input type="hidden" name="dir" value="<?= h($dirRel) ?>">
      <input type="file" name="files[]" id="fi" multiple hidden onchange="this.form.submit()">
      <button type="button" class="pri" onclick="fi.click()">⬆ Upload</button>
    </form>
    <button type="button" onclick="mk('mkdir')">📁 New folder</button>
    <button type="button" onclick="mk('newfile')">📄 New file</button>
    <button type="button" class="dan" onclick="bulkDel()">🗑 Delete selected</button>
    <span style="flex:1"></span>
    <input type="search" id="q" placeholder="Filter…" oninput="filt(this.value)">
  </div>
  <div style="overflow-x:auto">
  <table>
    <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.sel').forEach(c=>c.checked=this.checked)"></th>
      <th>Name</th><th>Size</th><th class="hide-sm">Modified</th><th class="hide-sm">Perms</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($dirRel !== ''): ?>
      <tr><td></td><td class="name" colspan="5"><a href="?d=<?= rawurlencode(rel_of(dirname($dir))) ?>">⬆ ..</a></td></tr>
    <?php endif; ?>
    <?php if (!$items): ?><tr><td></td><td colspan="5" class="mut">Empty folder. Drag files here to upload.</td></tr><?php endif; ?>
    <?php foreach ($items as $it):
        $rel = ltrim($dirRel . '/' . $it['n'], '/'); $u = rawurlencode($rel); ?>
      <tr class="row" data-n="<?= h(strtolower($it['n'])) ?>">
        <td><input type="checkbox" class="sel" value="<?= h($it['n']) ?>"></td>
        <td class="name"><?php if ($it['dir']): ?>📁 <a href="?d=<?= $u ?>"><b><?= h($it['n']) ?></b></a>
          <?php else: ?>📄 <?= is_editable_name($it['n']) ? '<a href="?edit=' . $u . '">' . h($it['n']) . '</a>' : h($it['n']) ?><?php endif; ?>
          <?= $it['link'] ? ' <span class="mut">↪ link</span>' : '' ?><?= $THIS === realpath($dir . '/' . $it['n']) ? ' <span class="mut">(this manager)</span>' : '' ?></td>
        <td class="mut"><?= $it['dir'] ? '—' : hsize((int)$it['size']) ?></td>
        <td class="mut hide-sm"><?= $it['mt'] ? date('Y-m-d H:i', $it['mt']) : '' ?></td>
        <td class="mut hide-sm"><code><?= h($it['perm']) ?></code></td>
        <td class="acts"><?php if (!$it['dir']): ?>
            <?php if (is_editable_name($it['n'])): ?><a class="btn" href="?edit=<?= $u ?>">Edit</a><?php endif; ?>
            <?php if (is_viewable_name($it['n'])): ?><a class="btn" href="?view=<?= $u ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
            <a class="btn" href="?dl=<?= $u ?>">⬇</a>
          <?php endif; ?>
          <button type="button" data-n="<?= h($it['n']) ?>" onclick="ren(this.dataset.n)">Rename</button>
          <button type="button" class="dan" data-n="<?= h($it['n']) ?>" onclick="del(this.dataset.n)">Delete</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="bar mut" style="border:0;font-size:12px">
    <?= count($items) ?> item(s) · free space <?= hsize((float)@disk_free_space($dir)) ?> ·
    max upload <?= h(ini_get('upload_max_filesize')) ?> · PHP <?= PHP_VERSION ?>
  </div>
</div>
<div id="drop">Drop files to upload</div>
<script>
const CSRF=<?= json_encode($_SESSION['csrf']) ?>,DIR=<?= json_encode($dirRel) ?>;
function post(d){const f=document.createElement('form');f.method='post';f.action=location.pathname;
  Object.assign(d,{csrf:CSRF,dir:DIR});
  for(const k in d)[].concat(d[k]).forEach(v=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=v;f.appendChild(i);});
  document.body.appendChild(f);f.submit();}
function ren(n){const v=prompt('Rename to:',n);if(v&&v!==n)post({do:'rename',item:n,newname:v});}
function del(n){if(confirm('Delete "'+n+'"?\nFolders are deleted with everything inside. This cannot be undone.'))post({do:'delete','items[]':n});}
function bulkDel(){const s=[...document.querySelectorAll('.sel:checked')].map(c=>c.value);
  if(!s.length)return alert('Nothing selected.');if(confirm('Delete '+s.length+' item(s)? This cannot be undone.'))post({do:'delete','items[]':s});}
function mk(t){const v=prompt(t==='mkdir'?'New folder name:':'New file name:');if(v)post({do:t,name:v});}
function filt(q){q=q.toLowerCase();document.querySelectorAll('tr.row').forEach(r=>r.style.display=r.dataset.n.includes(q)?'':'none');}
// drag & drop upload
const dz=document.getElementById('drop'),fi=document.getElementById('fi');let dc=0;
addEventListener('dragenter',e=>{if(e.dataTransfer.types.includes('Files')){dc++;dz.style.display='flex';}});
addEventListener('dragleave',()=>{if(--dc<=0){dc=0;dz.style.display='none';}});
addEventListener('dragover',e=>e.preventDefault());
addEventListener('drop',e=>{e.preventDefault();dc=0;dz.style.display='none';
  if(e.dataTransfer.files.length){fi.files=e.dataTransfer.files;document.getElementById('up').submit();}});
</script>
<?php foot();
