<?php

class Settings {
    private static $file;

    public static function loadSettingsFile() {
        if (empty(self::$file)) {
	        if (file_exists('settings.cfg')) {
		        self::$file = file_get_contents('settings.cfg');
	        } else {
		        self::$file = '';
		        file_put_contents('settings.cfg', '');
	        }
        }
    }

    public static function getPart($part) {
        Settings::loadSettingsFile();

        $data = array();
        $lines = explode("\n", self::$file);
        $reading = false;
        foreach ($lines as $key => $line) {
            if ($reading && $line != "" && substr(trim($line), 0, 2) != "//") {
                if (substr($line, 0, 1) == "[") {
                    break;
                }
                $parts = explode("=", $line);
                $index = array_shift($parts);
                $data[$index] = trim(implode("=", $parts));
            }

            if (strtoupper(trim($line)) == "[" . strtoupper($part) . "]") {
                $reading = true;
            }
        }
        return $data;
    }

    public static function getSetting($setting) {
        $name = explode("_", $setting);
        $data = Settings::getPart($name[0]);
        if (!empty($data[$name[1]])) {
            return $data[$name[1]];
        }
        return null;
    }

}


?>