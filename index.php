<?php
/**
 * Console
 * @author ºúÔóÃñ<huzemin8@126.com>
 * @date: 2015/9/12 14:42
 */

/*$pid = exec("php app.php > /dev/null 2>&1 & echo $!");

$fh = fopen("console.pid", "w+");
fwrite($fh, $pid);
fclose($fh);*/

include "LinuxProcess.php";


$cmds = array(
    "app" => "php app.php",
    "GO"  => "php app.php go"
);

$params = $_SERVER['argv'];

$lp = new LinuxProcess($cmds);
if(!isset($params[1])) {
    $params[1] = '';
}
switch($params[1]) {
    case 'all':
        if(isset($params[2]) && ($params[2] == 'true' || $params[2] == '1')) {
            $lp->runAll(true);
        } else {
            $lp->runAll();
        }
        echo "Run All app: finished!\n";
        break;
    case 'run':
        if(isset($params[2])) {
            if(isset($params[3])  && ($params[3] == 'true' || $params[3] == '1')) {
                $lp->run($params[2], true);
            } else {
                $lp->run($params[2]);
            }
            echo "Run app( {$params[2]} ): success!\n";
        } else {
            echo "Run app( {$params[2]} ): Fail! \n Can't find the app command!\n";
        }
        break;
    case 'destroy':
        $lp->destroy();
        echo "kill all process: success!\n";
        break;
    case 'list':
        $lp->getList(true);
        break;
    default:
        echo "Usage: php index.php [ all | destroy | run | list ] \n";
        echo "Manage all process that has been configed in file (config.php).\n\n";
        echo "Params info: \n";
        printf("%10s\t%s\n","all","run all the process.");
        printf("%10s\t%s\n","destroy","destroy all the process.");
        printf("%10s\t%s\n","run","run run the process.");
        printf("%10s\t%s\n","list","list all the process info.");
}

//$lp->destory();