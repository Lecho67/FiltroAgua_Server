<?php
/**
 * Excel reader for .xls files (BIFF formats)
 * Compatible with PHP 7 and 8
 * MIT License
 * Source: simplified version of Spreadsheet_Excel_Reader
 */

class Spreadsheet_Excel_Reader
{
    public $sheets = [];
    private $data;

    public function readString($data)
    {
        $this->data = $data;
        $this->parse();
    }

    private function parse()
    {
        $pos = 0;
        $data = $this->data;
        $length = strlen($data);

        $currentSheet = 0;
        $this->sheets[$currentSheet] = [
            'cells' => []
        ];

        while ($pos < $length) {
            $code = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
            $size = ord($data[$pos + 2]) | (ord($data[$pos + 3]) << 8);
            $pos += 4;

            $content = substr($data, $pos, $size);

            // BOF - start of sheet
            if ($code == 0x0809) {
                $currentSheet++;
                $this->sheets[$currentSheet] = [
                    'cells' => []
                ];
            }

            // LABEL - string cell
            elseif ($code == 0x0204) {
                $row = ord($content[0]) | (ord($content[1]) << 8);
                $col = ord($content[2]) | (ord($content[3]) << 8);
                $str = substr($content, 8);
                $this->sheets[$currentSheet]['cells'][$row][$col] = trim($str);
            }

            // NUMBER - numeric cell
            elseif ($code == 0x0203) {
                $row = ord($content[0]) | (ord($content[1]) << 8);
                $col = ord($content[2]) | (ord($content[3]) << 8);

                $num = unpack("d", strrev(substr($content, 6, 8)));
                $this->sheets[$currentSheet]['cells'][$row][$col] = $num[1];
            }

            $pos += $size;
        }
    }
}
