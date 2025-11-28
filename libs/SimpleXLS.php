<?php
// SimpleXLS: Read old Excel XLS files (BIFF) in pure PHP
// Based on: https://github.com/shuchkin/simplexls (MIT License)

class SimpleXLS
{
    public $sheets = [];

    public static function parse($file)
    {
        if (!is_readable($file)) return false;
        $xls = new self();
        return $xls->load($file) ? $xls : false;
    }

    public function load($file)
    {
        require_once __DIR__ . '/xls_reader.php';
        $data = file_get_contents($file);
        $reader = new Spreadsheet_Excel_Reader();
        $reader->readString($data);
        $this->sheets = $reader->sheets;
        return true;
    }

    public function rows($sheetIndex = 0)
    {
        if (!isset($this->sheets[$sheetIndex])) return [];
        return $this->sheets[$sheetIndex]['cells'];
    }
}
