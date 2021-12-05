<?php /** @noinspection PhpUnused */
/** @noinspection ReturnTypeCanBeDeclaredInspection */

/** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */

namespace Eftec\FileTextOne;


use DateTime;
use Exception;
use JsonException;
use RuntimeException;

/**
 * FileTextOne
 *
 * @package   FileTextOne
 * @author    Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @copyright Copyright (c) 2021 Jorge Patricio Castro Castillo MIT License.
 *            Don't delete this comment, its part of the license.
 *            Part of this code is based in the work of Laravel PHP Components.
 * @version   4.1
 * @link      https://github.com/EFTEC/FileTextOne
 */
class FileTextOne
{
    /** @var string=['csv','json'][$i] */
    public $type = '';
    public $file = '';
    public $columns;
    /** @var array=[]['auto','int','decimal','datetime','string][$i] */
    public $columnTypes;
    /** @var array|null [$csvSeparator, $quotes, $header, $newLine]; */
    public $style = [',','"',true,"\n"];
    public $regionDecimal = '.';
    public $regionDateTime = 'Y-m-d H:i:s';
    public $regionDate = 'Y-m-d';
    /** @var null used by json */
    public $initialField;
    public $lastError;

    /**
     * @param string     $type =['csv','json'][$i]
     * @param array      $columns
     * @param null|array $extra
     */
    public function __construct($type, $file, $columns = [], $extra = null)
    {
        $this->type = $type;
        $this->file = $file;
        if (is_array($columns) && count($columns) > 0 && isset($columns[0])) {
            // is not an associative array
            $this->columns = $columns;
            $this->columnTypes = array_fill(0, count($columns), 'auto');
        }

        $this->style = $extra ?? $this->style;
    }

    public function regional($decimalseparator = '.', $date = 'Y-m-d', $datetime = 'Y-m-d H:i:s')
    {
        $this->regionDecimal = $decimalseparator;
        $this->regionDate = $date;
        $this->regionDateTime = $datetime;
    }

    public function setCsvStyle($csvSeparator = ',', $quotes = '"', $header = true, $newLine = "\n")
    {
        $this->style = [$csvSeparator, $quotes, $header, $newLine];
    }

    /**
     * @param null|string $initialField  (for json), you can start reading the values starting in a field<br>
     *                                   <b>Example:</b><br>
     *                                   <pre>
     *                                   $this->toAll('listfield');
     *                                   // { 'name':'json','listfield':{'id':1,'id':2}}
     *                                   // it returns [[id->1],[id->2]]
     *                                   </pre>
     * @return array
     * @throws Exception
     */
    public function toAll($initialField = null)
    {
        $contentNotProcessed = $this->read();
        $this->initialField=$initialField;
        switch ($this->type) {
            case 'json':
                return $this->toAllJSON($contentNotProcessed,$initialField);
            case 'csv':
                return $this->toAllCSV($contentNotProcessed);
        }
        throw new RuntimeException('type not defined');
    }
    private function toAllCSV(&$lines) {
        $result = [];
        $numLines = count($lines);
        if ($numLines > 0) {
            if ($this->style[2]) {
                // it has header
                $this->columns = $this->splitLine($lines[0], false);
                $first = 1;
            } else {
                $this->columns = null;
                $first = 0;
            }
            if (isset($lines[$first])) {
                // asume types
                $firstLine = $this->splitLine($lines[$first], true);
                //$numcols=count($firstLine);
                $this->determineTypes($firstLine);
                for ($i = $first; $i < $numLines; $i++) {
                    $tmp = $this->splitLine($lines[$i], true);
                    foreach ($tmp as $namecol => $item) {
                        if (!isset($this->columnTypes[$namecol])) {
                            throw new RuntimeException('incorrect column found in csv line ' . $i);
                        }
                        $tmp[$namecol] = $this->convertType($item, $this->columnTypes[$namecol]);
                    }
                    if (count($tmp) > 0) {
                        // we avoid inserting an empty line
                        $result[] = $tmp;
                    }
                }
            }
        }
        return $result;
    }
    private function toAllJSON(&$alljson,&$initialField) {

        if ($initialField !== null) {
            if(isset($alljson[$initialField])) {
                $alljson=$alljson[$initialField];
            } else {
                throw new RuntimeException("field [$initialField] does not exists in json");
            }
        }
        if($alljson===false || !is_array($alljson)) {
            return false;
        }
        if(count($alljson)===0) {
            return $alljson;
        }
        $this->columns=array_keys($alljson);
        $firstLine = $alljson[0];
        $this->determineTypes($firstLine);
        foreach($alljson as $k=>$line) {
            foreach($line as $namecol=>$cell) {
                $alljson[$k][$namecol] = $this->convertType($cell, $this->columnTypes[$namecol]);
            }
        }
        return $alljson;
    }
    private function determineTypes(&$row) {
        $this->columnTypes = [];
        foreach ($row as $namecol => $item) {
            $this->columnTypes[$namecol] = $this->getType($item);
        }
    }

    /**
     * @return false|array
     * @throws JsonException
     */
    private function read()
    {
        try {
            $content = @file_get_contents($this->file);
            if($content===false) {
                throw new RuntimeException('file not found');
            }
        } catch(Exception $ex) {
            $this->lastError=$ex->getMessage();
            $content='';
        }
        switch ($this->type) {
            case 'json':
                return json_decode($content, true, 512, JSON_THROW_ON_ERROR || JSON_NUMERIC_CHECK);
            case 'csv':
                return explode($this->style[3], $content);
        }
        return false;
    }

    private function splitLine($lineTxt, $useColumn)
    {
        if ($lineTxt === null || $lineTxt === '') {
            return [];
        }
        $arr = str_getcsv($lineTxt, $this->style[0], $this->style[1]);
        $result = [];
        if ($useColumn === true) {
            foreach ($arr as $k => $v) {
                if (!isset($this->columns[$k])) {
                    $this->columns[$k] = 'col' . ($k + 1); // column is missing so we create a column name
                }
                $result[$this->columns[$k]] = trim($v);

            }
        } else {
            foreach ($arr as $k => $v) {
                $result[$k] = trim($v);
            }
        }
        return $result;
    }

    public function getType($input)
    {
        // array
        if (is_array($input)) {
            return 'array';
        }
        // int?
        if ($this->isInt($input)) {
            return 'int';
        }
        // decimal?
        if ($this->regionDecimal !== '.') {
            $inputD = str_replace($this->regionDecimal, '.', $input);
        } else {
            $inputD = $input;
        }
        if (is_numeric($inputD)) {
            return 'decimal';
        }
        // datetime?
        $inputD = ($input instanceof DateTime)? $input : DateTime::createFromFormat($this->regionDateTime, $input);
        if ($inputD instanceof DateTime) {
            return 'datetime';
        }
        // date
        $inputD = DateTime::createFromFormat($this->regionDate, $input);
        if ($inputD instanceof DateTime) {
            return 'date';
        }
        return is_string($input) ? 'string' : 'object';
    }

    public function isInt($input)
    {
        if ($input === null || $input === '') {
            return false;
        }
        if(is_int($input)) {
            return true;
        }
        if(is_float($input) || is_object($input)) {
            return false;
        }
        if ($input[0] === '-') {
            return ctype_digit(substr($input, 1));
        }
        return ctype_digit($input);
    }

    public function convertTypeBack($input, $type)
    {
        switch ($type) {
            case 'int':
                return $input;
            case 'decimal':
                if ($this->regionDecimal !== '.') {
                    $inputD = str_replace( '.',$this->regionDecimal, $input);
                } else {
                    $inputD = $input;
                }
                return $inputD;
            case 'date':
                if($this->type==='csv') {
                    return $this->style[1].$input->format($this->regionDate).$this->style[1];
                }
                return $input->format($this->regionDate);
            case 'string':
                if($this->type==='csv') {
                    return $this->style[1].$input.$this->style[1];
                }
                return $input;
            case 'datetime':
                if($this->type==='csv') {
                    return $this->style[1].$input->format($this->regionDateTime).$this->style[1];
                }
                return $input->format($this->regionDateTime);
        }
        return $input;
    }

    public function convertType($input, $type)
    {
        switch ($type) {
            case 'int':
                return (int)$input;
            case 'decimal':
                if ($this->regionDecimal !== '.') {
                    $inputD = str_replace($this->regionDecimal, '.', $input);
                } else {
                    $inputD = $input;
                }
                return (float)$inputD;
            case 'string':
                return (string)$input;
            case 'date':
                return DateTime::createFromFormat($this->regionDate, $input);
            case 'datetime':
                return DateTime::createFromFormat($this->regionDateTime, $input);
        }
        return $input;
    }

    public function flush() {
        return unlink($this->file);
    }

    /**
     * @param $row
     * @param $initialField
     * @return bool
     * @throws JsonException
     */
    public function insert($row,$initialField=null)
    {
        if($row===null) {
            return false;
        }
        // is row multiple or not? multiple means, we are inserting many rows.
        if(!(isset($row[0]) && is_array($row[0]))) {
            $row=[$row];
        }
        if($this->columnTypes===null) {
            $this->determineTypes($row[0]);
        }
        if($this->columns===null) {
            $this->columns=[];
            foreach($row[0] as $k=>$v) {
                if(is_numeric($k)) {
                    $k='col'.($k+1);
                }
                $this->columns[$k]=$k;
            }
        }

        switch ($this->type) {
            case 'csv':
                return $this->insertCSV($row);
            case 'json':
                $initialField= $initialField ?? $this->initialField;
                return $this->insertJSON($row,$initialField);
        }
        return false;
    }

    private function insertCSV(&$rows) {
        $fp=fopen($this->file, 'ab+');
        $size=filesize($this->file);
        if(!$size && $this->style[2]===true) {
            $line=[];
            foreach($this->columns as $col) {
                $line[]=$this->convertTypeBack($col, 'string');
            }
            fwrite($fp,implode($this->style[0],$line).$this->style[3]);
        }
        $line=[];
        foreach($rows as $kr=>$row) {
            foreach ($row as $k => $v) {
                $line[$kr][$k] = $this->convertTypeBack($v, $this->columnTypes[$k]);
            }
            fwrite($fp,implode($this->style[0],$line[$kr]).$this->style[3]);
        }
        fclose($fp);
        return true;
    }

    /**
     * @param $rows
     * @param $initialField
     * @return bool
     * @throws JsonException
     */
    private function insertJSON(&$rows,$initialField=null) {
        $this->initialField= $initialField ?? $this->initialField;
        $contentNotProcessed=$this->read();
        if($contentNotProcessed===false) {
            // new file
            $contentNotProcessed=[];
        }
        $lines=[];
        foreach($rows as $kr=>$row) {
            foreach ($row as $k => $v) {
                $lines[$kr][$k] = $this->convertTypeBack($v, $this->columnTypes[$k]);
            }
        }

        if($initialField) {
            foreach($lines as $line) {
                $contentNotProcessed[$initialField][] =$line;
            }
        } else {
            foreach($lines as $line) {
                $contentNotProcessed[] =$line;
            }
        }
        $result=file_put_contents($this->file, json_encode($contentNotProcessed, JSON_THROW_ON_ERROR));
        return $result!==false;
    }
}