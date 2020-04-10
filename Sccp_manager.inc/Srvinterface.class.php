<?php

namespace FreePBX\modules\Sccp_manager;

class Srvinterface
{

    var $error;
    var $_info;
    var $ami_mode;

    public function __construct($parent_class = null)
    {
        $this->parent_class = $parent_class;
        if ($this->parent_class == null) {
            $this->parent_class = $this;
        }
        $this->error = "";
        $driverNamespace = "\\FreePBX\\Modules\\Sccp_manager";
        $drivers = array('aminterface' => 'aminterface.class.php', 'oldinterface' => 'oldinterface.class.php');
        $ami_mode = false;
        foreach ($drivers as $key => $value) {
            $class = $driverNamespace . "\\" . $key;
            $driver = __DIR__ . "/aminterface/" . $value;
            //error_log("$class, $driver");
            if (!class_exists($class, false) && file_exists($driver)) {
                include_once($driver);
            } else {
                throw new \Exception("include file($key) required but file not found " . $driver);
            }
            if (class_exists($class, false)) {
                $this->$key = new $class($this->parent_class);
                $parent_class->$key = $this->$key;
                $this->_info [] = $this->$key->info();
            } else {
                throw new \Exception("Invalid Class inside in the include folder");
            }
        }
        if ($this->aminterface->status()) {
            $this->aminterface->open();
        }
        $this->ami_mode = $this->aminterface->status();
    }

    public function info()
    {
        $Ver = '14.0.1';
        $info = '';
        foreach ($this->_info as $key => $value) {
            $info .= $value['about'] . "\n  ";
        }
        return array('Version' => $Ver,
            'about' => 'Server interface data ver: ' . $Ver . "\n  " . $info);
    }

    public function sccpDeviceReset($id = '')
    {
        if ($this->ami_mode) {
            return $this->aminterface->sccpDeviceReset($id, 'reset');
        } else {
            return $this->oldinterface->amiCommandSwitch(array('cmd' => 'reset_phone', 'name' => $id));
        }
    }

    public function sccpDeviceRestart($id = '')
    {
        if ($this->ami_mode) {
            return $this->aminterface->sccpDeviceReset($id, 'restart');
        } else {
            return $this->oldinterface->amiCommandSwitch(array('cmd' => 'reset_phone', 'name' => $id));
        }
    }

    public function sccpAcceptToken($id = '')
    {
        if ($this->ami_mode) {
            return $this->aminterface->sccpDeviceReset($id, 'tokenack');
        } else {
            return $this->oldinterface->amiCommandSwitch(array('cmd' => 'reset_token', 'name' => $id));
        }
    }

    public function sccpReload()
    {
        if ($this->ami_mode) {
            return $this->aminterface->sccpReload();
        } else {
            return $this->oldinterface->amiCommandSwitch(array('cmd' => 'sccpReload'));
        }
    }

    public function sccpReloadLine($id = '')
    {
        if ($this->ami_mode) {
            /* !DdG!: currently the is no chan-sccp AMI command for this (yet), this should be added in the next chan-sccp release */
            return $this->oldinterface->amiCommandSwitch(array('cmd' => 'reload_line', 'name' => $id));
        } else {
            return $this->oldinterface->amiCommandSwitch(array('cmd' => 'reload_line', 'name' => $id));
        }
    }

    private function amiCommandSwitch($params = array())
    {
        if ($this->ami_mode && !empty($params['cmd'])) {
            switch ($params['cmd']) {
                case 'reset_phone':
                    return $this->aminterface->sccpDeviceReset($params['name'], 'reset');
                case 'restart_phone':
                    return $this->aminterface->sccpDeviceReset($params['name'], 'restart');
                case 'reload_phone':
                    return $this->aminterface->sccpDeviceReset($params['name'], 'full');
                case 'reset_token':
                    return $this->aminterface->sccpDeviceReset($params['name'], 'tokenack');
                case 'reload_line':
                    return $this->sccpReloadLine();
                case 'get_softkey':
                case 'get_device':
                case 'get_hints':
                case 'get_dev_info':
                    print_r($params);
                    throw new \Exception("Invalid Class inside in the include folder" . $params['cmd']);
                    die();
            }
            // fall through if not returned
        }
        return $this->oldinterface->amiCommandSwitch($params);
    }

    /**
     * !TODO!: 
     * these following functions are just wrappers around
     * simple functions defined in aminterface.
     */
    public function getSccpDeviceInformation($dev_id)
    {
        if (empty($dev_id)) {
            return array();
        }
        if ($this->ami_mode) {
            return $this->aminterface->getSccpDeviceInformation($dev_id);
        } else {
            return $this->oldinterface->getSccpDeviceInformation($dev_id);
        }
    }

    public function getHints()
    {
        if ($this->ami_mode) {
            return $this->aminterface->getHints();
        } else {
            return $this->oldinterface->getHints();
        }
    }

    public function getAllHints()
    {

        if ($this->ami_mode) {
            return $this->aminterface->getAllHints();
        } else {
            return $this->oldinterface->getAllHints();
        }
    }

    public function getRealtimeStatus()
    {
        if (!$this->ami_mode) {
            return $this->oldinterface->getRealtimeStatus();
        } else {
            $ast_out = $this->aminterface->getRealtimeStatus();
            if (is_array($ast_out)) {
                foreach ($ast_out as $aline) {
                    if (strlen($aline) > 3) {
                        $ast_key = strstr(trim($aline), ' ', true);
                        $ast_res[$ast_key] = array('message' => $aline, 'status' => strpos($aline, 'connected') ? 'OK' : 'ERROR');
                    }
                }
            }
            return $ast_res;
        }
    }

    /**
     * !TODO! would be nice if we could name this function 'isCompatible()' and have it return true/false 
     */
    public function getSccpVersionCode()
    {
        $res = $this->getSccpVersion();
        if (empty($res)) {
            return 0;
        }
        switch ($res["vCode"]) {
            case 0:
                return 0;
            case 433:
                return 433;
            case 432:
            case 431:
                return 431;
            default:
                return 430;
        }
    }

    public function getSccpVersion()
    {
        $res = $this->getChanSccpVersion();
        if (empty($res)) {
            $res = $this->oldinterface->getCoreSCCPVersion();
        }
        return $res;
    }

    public function getSoftkeySets()
    {

        if ($this->ami_mode) {
            return $this->aminterface->getSoftkeySets();
        } else {
            return $this->oldinterface->getSoftkeySets();
        }
    }

    public function getRegisteredDevices()
    {
        if ($this->ami_mode) {
            return $this->aminterface->getRegisteredDevices();
        } else {
            return $this->oldinterface->getRegisteredDevices();
        }
    }

    function getChanSccpVersion()
    {
        if (!$this->ami_mode) {
            return $this->oldinterface->getChanSccpVersion();
        } else {
            $result = array();
            $metadata = $this->aminterface->getSccpVersion();

            if ($metadata && array_key_exists("Version", $metadata)) {
                $result["Version"] = $metadata["Version"];
                $version_parts = explode(".", $metadata["Version"]);
                $result["vCode"] = 0;
                if ($version_parts[0] == "4") {
                    switch ($version_parts[1]) {
                        case "1":
                            $result["vCode"] = 410;
                            break;
                        case "2":
                            $result["vCode"] = 420;
                            break;
                        case 3. . .5:
                            $result["vCode"] = 430;
                            break;
                        default:
                            $result["vCode"] = 400;
                            break;
                    }
                }

                /* Revision got replaced by RevisionHash in 10404 (using the hash does not work) */
                if (array_key_exists("Revision", $metadata)) {
                    if (base_convert($metadata["Revision"], 16, 10) == base_convert('702487a', 16, 10)) {
                        $result["vCode"] = 431;
                    }
                    if (base_convert($metadata["Revision"], 16, 10) >= "10403") {
                        $result["vCode"] = 431;
                    }
                }
                if (array_key_exists("RevisionHash", $metadata)) {
                    $result["RevisionHash"] = $metadata["RevisionHash"];
                } else {
                    $result["RevisionHash"] = '';
                }
                if (array_key_exists("RevisionNum", $metadata)) {
                    $result["RevisionNum"] = $metadata["RevisionNum"];
                    if ($metadata["RevisionNum"] >= "10403") { // new method, RevisionNum is incremental
                        $result["vCode"] = 432;
                    }
                    if ($metadata["RevisionNum"] >= "10491") { // new method, RevisionNum is incremental
                        $result["vCode"] = 433;
                    }
                }
                if (array_key_exists("ConfigureEnabled", $metadata)) {
                    $result["futures"] = implode(';', $metadata["ConfigureEnabled"]);
                }
            } else {
                return null;
                die_freepbx("Version information could not be retrieved from chan-sccp, via astman::SCCPConfigMetaData");
            }
            return $result;
        }
    }

    // ---------------------------- Debug Data -------------------------------------------
    function t_get_ami_data()
    {
        global $amp_conf;
        $fp = fsockopen("127.0.0.1", "5038", $errno, $errstr, 10);
        if (!$fp) {
            echo "$errstr ($errno)<br />\n";
        } else {
            $time_connect = microtime_float();
            fputs($fp, "Action: login\r\n");
            fputs($fp, "Username: " . $amp_conf[AMPMGRUSER] . "\r\n");
            fputs($fp, "Secret: " . $amp_conf[AMPMGRPASS] . "\r\n");
            fputs($fp, "Events: on\r\n\r\n");
/*
            fputs($fp, "Action: SCCPShowDevice\r\n");
            fputs($fp,"Segment: general\r\n");
            fputs($fp,"DeviceName: SEP00070E36555C\r\n");
            fputs ($fp,"Action: DeviceStateList\r\n");
*/
            fputs($fp, "Action: SCCPShowDevices\r\n");
            fputs($fp, "Segment: general\r\n");
/*
            fputs ($fp,"Action: SCCPShowDevice\r\n");
            fputs ($fp,"DeviceName: SEP00070E36555C\r\n");

            fputs($fp, "Action: ExtensionStateList\r\n");
            fputs($fp, "Action: ExtensionStateList\r\n");
            fputs($fp, "Command: sccp show version\r\n");
            fputs($fp, "Command: core show hints\r\n");
            fputs ($fp,"Segment: general\r\n");
            fputs ($fp,"Segment: general\r\n");
            "Segments":["general","device","line","softkey"]}
            fputs ($fp,"Segment: device\r\n");
            fputs ($fp,"ResultFormat: command\r\n");
 */
            fputs($fp, "\r\n");
            $time_send = microtime_float();
            /*
            fputs ($fp,"Action: SCCPConfigMetaData\r\n");
            fputs ($fp,"\r\n");
            fputs ($fp,"Action: SCCPConfigMetaData\r\n");
            fputs ($fp,"Segment: general\r\n");
            fputs ($fp,"\r\n");
            fputs ($fp,"Action: SCCPConfigMetaData\r\n");
            fputs ($fp,"Segment: general\r\n");
            fputs ($fp,"ListResult: yes\r\n");
            fputs ($fp,"Option: fallback\r\n");
            fputs ($fp,"\r\n");
            fputs ($fp,"Action: SCCPConfigMetaData\r\n");
            fputs ($fp,"Segment: device\r\n");
            fputs ($fp,"ListResult: freepbx\r\n");
            fputs ($fp,"\r\n");
            fputs ($fp,"Action: SCCPConfigMetaData\r\n");
            fputs ($fp,"Segment: device\r\n");
            fputs ($fp,"Option: dtmfmode\r\n");
            fputs ($fp,"ListResult: yes\r\n");
            fputs ($fp,"\r\n");
            */
            fputs($fp, "Action: logoff\r\n\r\n");
            $time_logoff = microtime_float();
            
            $resp = '';
            while (!feof($fp)) {
                $resp .= fgets($fp);
            }
            $time_resp = microtime_float();
            $resp .= "\r\n\r\n Connect :".($time_send - $time_connect). " Logoff :".($time_logoff- $time_send). " Response :".($time_resp-$time_logoff)."\r\n\r\n ";
        }
        fclose($fp);
        return $resp;
    }
}
