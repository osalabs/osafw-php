<?php
@session_start();
if (!isset($CONFIG)) {
    require_once dirname(__DIR__) . "/configs/config.php";
}

if (($_SESSION['user_id'] ?? 0) && ($_SESSION['access_level'] ?? 0) == 100) {
    $dump_dir = rtrim($CONFIG['PUBLIC_UPLOAD_DIR'], '/\\') . '/dbdumps';
    if (!is_dir($dump_dir)) {
        @mkdir($dump_dir, 0770, true);
    }
    $DUMP_FILE = $dump_dir . '/pmadump';

    $DBDEF = array(
        'user'  => $CONFIG['DB']['USER'], #required
        'pwd'   => $CONFIG['DB']['PWD'], #required
        'db'    => $CONFIG['DB']['DBNAME'], #optional, default DB
        'host'  => $CONFIG['DB']['HOST'], #optional
        'port'  => $CONFIG['DB']['PORT'], #optional
        'chset' => "utf8", #optional, default charset
    );
    $_SESSION['is_logged'] = true;
    loadcfg();
    if (!isset($_REQUEST['q'])) {
        $_REQUEST['XSS'] = $_SESSION['XSS'];
        $_REQUEST['q']   = b64e('SHOW TABLE STATUS');
    }
} else {
    $ACCESS_PWD = ''; #set Access password here to enable access to database as non-logged admin
    if (!$ACCESS_PWD) {
        rw("Set \$ACCESS_PWD or login to site as an Administrator");
        exit;
    }
}

?>
