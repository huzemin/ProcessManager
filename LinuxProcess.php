<?php
/**
 * Console
 * @author 胡泽民<huzemin8@126.com>
 * @date: 2015/9/12 15:00
 */

class LinuxProcess {
    /**
     * 执行的Linux 命令
     * @var string
     */
    private $cmds = array();

    /**
     * 执行命令的进程PID
     */
    private $pids =  array();

    private $after_cmd = " > /dev/null 2>&1 & echo $!";
    /**
     * 命令进程PID保存方案
     */
    private $pid_file_name = "linux_process.pid";

    public function __construct($cmds = array()) {
        if(!$this->isLinux()) {
            throw new Exception('Current System ('.PHP_OS.') is not Linux.');
        }

        if(!is_array($cmds)) {
            throw new Exception('The params must be Array');
        }

        $this->cmds = $cmds;
        // 恢复上次执行的进程PID
        if(!empty($this->cmds)) {
            $this->restoreState();
        }
    }

    /**
     * 判断是否为Linux系统
     * @return bool
     */
    public function isLinux() {
        /*if(in_array(PHP_OS, array('Linux',''))) {
            return true;
        }*/
        if(PATH_SEPARATOR == ':') {
            return true;
        }
        return false;
    }

    public function setCmds($cmds) {
        $this->cmds = $cmds;
        // 恢复上次执行的进程PID
        if(!empty($this->cmds)) {
            $this->restoreState();
        }
    }

    public function getCmds($cmds) {
        return $this->cmds;
    }

    public function getPids() {
        return $this->pids;
    }

    /**
     * 运行所有的命令
     * @param bool|false $force  如果命令已经被执行，是否强制重新执行
     */
    public function runAll($force = false) {
        foreach($this->cmds as $key => $cmd) {
            $pid_key = md5($key.'|'. $cmd);
            if(isset($this->pids[$pid_key]) && $this->getPidCMD($this->pids[$pid_key])) {
                if($force) {
                    $this->kill($this->pids[$pid_key]);
                    unset($this->pids[$pid_key]);
                } else {
                    continue;
                }
            }
            $cmd = $cmd . $this->after_cmd;
            $pid = exec($cmd);
            $this->pids[$pid_key] = $pid;
        }
        $this->saveState();
    }

    public function run($name, $force = false) {
        if(isset($this->cmds[$name])) {
            $cmd = $this->cmds[$name];
            $pid_key = md5($name.'|'. $cmd );
            if(isset($this->pids[$pid_key])
                && $this->getPidCMD($this->pids[$pid_key]) === $cmd
            ) {
                    if($force) {
                    $this->kill($this->pids[$pid_key]);
                    unset($this->pids[$pid_key]);
                } else {
                    return;
                }
            }
            $cmd = $cmd . $this->after_cmd;
            $pid = exec($cmd);
            $this->pids[$pid_key] = $pid;
        }
        $this->saveState();
    }

    /**
     * 通过PID终止进程
     * @param $pid
     */
    public function kill($pid) {
        if($this->pidExist($pid)){
            $kill = "kill $pid";
            shell_exec($kill);
        }
    }

    public function getList($print = false) {
        if($print) {
            printf("\033[1m %-5s %-5s %-10s %-8s %-12s %-35s %-s \033[0m \n","PID","STATE",'TIME',"SN","APP_NAME","PID_KEY","COMMAND");
        }
        $_cmds = array();
        foreach($this->cmds as $key => $cmd) {
            $pid_key = md5($key . '|'. $cmd);
            $_cmds[$key]['cmd'] = $cmd;
            $_cmds[$key]['app_name'] = $key;
            if(isset($this->pids[$pid_key]) && $this->getPidCMD($this->pids[$pid_key])) {
                $_cmds[$key]['state'] = 1;
                $_cmds[$key]['state_name'] = 'Running';
                $_cmds[$key]['time'] = $this->getPidParam($this->pids[$pid_key]);
            } else {
                $_cmds[$key]['time'] = '00:00:00';
                $_cmds[$key]['state'] = 0;
                $_cmds[$key]['state_name'] = '..';
            }
            $_cmds[$key]['pid'] = isset($this->pids[$pid_key]) ? $this->pids[$pid_key]: 0;
            if($print) {
                if($_cmds[$key]['state'] != 0) {
                    $format = "\033[1;32m %-5s %-5s %-10s %-8s %-12s %-35s %-s \033[0m \n";
                } else {
                    $format = "%-5s %-5s %-10s %-8s %-12s %-35s %-s \n";
                }
                printf($format,
                    $_cmds[$key]['pid'],$_cmds[$key]['state'],$_cmds[$key]['time'],
                    $_cmds[$key]['state_name'],$_cmds[$key]['app_name'],$pid_key,
                    $_cmds[$key]['cmd']);
            }
        }
        return $_cmds;
    }

    /**
     * 终止所有的进程
     */
    public function destroy() {
        foreach($this->pids as $pid) {
            $this->kill($pid);
            unset($this->pids[$pid]);
        }
        $this->saveState();
    }

    /**
     * 判断进程是否存在
     * @param $pid
     * @return bool
     */
    public function pidExist($pid) {
        // 通过进程PID和对比执行命令，判断进程是否正在运行
        if(is_numeric($pid)) {
            @exec("ps $pid", $state);
            if(count($state) >= 2) {
                return true;
            }
        }
        return false;
    }

    /**
 * 获取进程执行命令
 * @param $pid
 * @return array|bool
 */
    public function getPidCMD($pid) {
        @exec("ps -o command $pid", $state);
        if(count($state) >= 2) {
            $command = $state[1];
            return $command;
        }
        return false;
    }

    /**
     * 获取进程执行命令
     * @param $pid
     * @return array|bool
     */
    public function getPidParam($pid, $params = 'time') {
        $params = strtolower($params);
        if(in_array($params, array('time', 'command','cmd', 'uid','pid','ppid','pri','stat','tty'))){
            @exec("ps -o $params $pid", $state);
            if(count($state) >= 2) {
                $command = $state[1];
                return $command;
            }
        }
        return false;
    }
    /**
     * 通过文件重新恢复进程状态
     * @return array
     */
    public function restoreState() {
        if(file_exists($this->pid_file_name)) {
            $pids_content = file_get_contents($this->pid_file_name);
            $pids_line = explode("\n", $pids_content);
            foreach($pids_line as $line) {
                if(!empty($line)) {
                    $pids = explode("=", $line);
                    if(count($pids) == 2) {
                        if(in_array($this->getPidCMD($pids[1]), $this->cmds)){
                            $this->pids[$pids[0]] = $pids[1];
                        }
                    }
                }
            }
        }
        return $this->pids;
    }

    public function saveState() {
        // 判断文件是否可以写
        $pids = '';
        foreach($this->pids as $pid_key =>$pid) {
            $pids .= "$pid_key=$pid\n";
        }
        $fh = fopen($this->pid_file_name, 'w+');
        fwrite($fh, $pids);
        fclose($fh);
    }
}