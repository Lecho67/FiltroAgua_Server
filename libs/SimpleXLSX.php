<?php
// SimpleXLSX: Read XLSX files in pure PHP (MIT)
// Compatible with PHP 8.x
class SimpleXLSX
{
    private $sheet;

    public static function parse($file)
    {
        if (!is_readable($file)) return false;
        $xlsx = new self();
        return $xlsx->load($file) ? $xlsx : false;
    }

    public function load($file)
    {
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) return false;

        $sharedStrings = [];
        $shared = $zip->getFromName('xl/sharedStrings.xml');

        if ($shared) {
            $xml = simplexml_load_string($shared);
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) $text .= (string)$si->t;
                if (isset($si->r)) {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) return false;

        $xml = simplexml_load_string($sheetXml);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $r = [];
            foreach ($row->c as $c) {
                $attr = $c->attributes();
                $type = isset($attr['t']) ? (string)$attr['t'] : '';
                $value = isset($c->v) ? (string)$c->v : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                }

                $r[] = $value;
            }
            $rows[] = $r;
        }

        $this->sheet = $rows;
        return true;
    }

    public function rows()
    {
        return $this->sheet;
    }
}
