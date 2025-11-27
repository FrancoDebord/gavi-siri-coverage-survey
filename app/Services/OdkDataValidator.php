<?php

namespace App\Services;

use League\Csv\Reader;

class OdkDataValidator
{
    protected array $rules = [];

    public function __construct()
    {
        $path = storage_path('app/public/odk/xlsform_rules.json');
        $this->rules = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
    }

    /**
     * Validate a single CSV file and return array of errors.
     * Each error: [file, row_key, field, issue, value, constraint?]
     */
    public function validateCsv(string $csvFile, string $label = null): array
    {
        $errors = [];
        if (!file_exists($csvFile)) return $errors;

        $csv = Reader::createFromPath($csvFile, 'r');
        $csv->setHeaderOffset(0);
        $rows = iterator_to_array($csv->getRecords());

        $evaluator = new ConstraintEvaluator();

        foreach ($rows as $idx => $row) {
            $rowKey = $row['KEY'] ?? ($row['main_key'] ?? 'row_'.$idx);

            // Basic per-field validations from rules
            foreach ($this->rules as $fieldName => $rule) {
                // If field isn't in CSV at all, skip
                if (!array_key_exists($fieldName, $row)) continue;

                $value = $row[$fieldName];

                // Required
                if (!empty($rule['required']) && ($value === null || $value === '')) {
                    $errors[] = [
                        'file' => $label ?? basename($csvFile),
                        'row_key' => $rowKey,
                        'field' => $fieldName,
                        'issue' => 'required_missing',
                        'value' => $value,
                    ];
                    continue;
                }

                // Type integer check (basic)
                if ($rule['type'] && stripos($rule['type'], 'int') !== false) {
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $errors[] = [
                            'file' => $label ?? basename($csvFile),
                            'row_key' => $rowKey,
                            'field' => $fieldName,
                            'issue' => 'invalid_integer',
                            'value' => $value,
                        ];
                    }
                }

                // Geopoint (if type contains geopoint)
                if ($rule['type'] && stripos($rule['type'], 'geopoint') !== false) {
                    if ($value === null || $value === '') {
                        $errors[] = [
                            'file' => $label ?? basename($csvFile),
                            'row_key' => $rowKey,
                            'field' => $fieldName,
                            'issue' => 'missing_geopoint',
                            'value' => $value,
                        ];
                    } else {
                        $parts = preg_split('/\s+/', trim($value));
                        if (count($parts) < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
                            $errors[] = [
                                'file' => $label ?? basename($csvFile),
                                'row_key' => $rowKey,
                                'field' => $fieldName,
                                'issue' => 'invalid_geopoint',
                                'value' => $value,
                            ];
                        }
                    }
                }

                // Constraint evaluation (if present)
                if (!empty($rule['constraint']) && ($value !== null || $value !== '')) {
                    $expr = $rule['constraint'];
                    // Evaluate constraint in a sandboxed evaluator using the current row
                    try {
                        $ok = $evaluator->evaluate($expr, $row);
                        if ($ok === false) {
                            $errors[] = [
                                'file' => $label ?? basename($csvFile),
                                'row_key' => $rowKey,
                                'field' => $fieldName,
                                'issue' => 'constraint_failed',
                                'constraint' => $expr,
                                'value' => $value,
                            ];
                        } elseif ($ok === null) {
                            // unable to evaluate -> warn (not fatal)
                            $errors[] = [
                                'file' => $label ?? basename($csvFile),
                                'row_key' => $rowKey,
                                'field' => $fieldName,
                                'issue' => 'constraint_not_evaluated',
                                'constraint' => $expr,
                                'value' => $value,
                            ];
                        }
                    } catch (\Throwable $t) {
                        $errors[] = [
                            'file' => $label ?? basename($csvFile),
                            'row_key' => $rowKey,
                            'field' => $fieldName,
                            'issue' => 'constraint_error',
                            'constraint' => $expr,
                            'value' => $value,
                            'message' => $t->getMessage(),
                        ];
                    }
                }
            }

            // Additional generic checks:
            if ((isset($row['current_location-Latitude']) && $row['current_location-Latitude'] !== '')
                xor (isset($row['current_location-Longitude']) && $row['current_location-Longitude'] !== '')) {
                $errors[] = [
                    'file' => $label ?? basename($csvFile),
                    'row_key' => $rowKey,
                    'field' => 'current_location',
                    'issue' => 'incomplete_geolocation',
                    'value' => json_encode([
                        'lat' => $row['current_location-Latitude'] ?? null,
                        'lng' => $row['current_location-Longitude'] ?? null
                    ]),
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate multiple files and persist errors_report.json
     * $files: array of ['path'=>..., 'label'=>...]
     */
    public function validateAll(array $files): array
    {
        $all = [];
        foreach ($files as $f) {
            $all = array_merge($all, $this->validateCsv($f['path'], $f['label']));
        }
        // persist
        $out = storage_path('app/public/odk/errors_report.json');
        file_put_contents($out, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $all;
    }
}

/**
 * Simple, secure constraint evaluator supporting:
 * - numeric/string literals
 * - field references ${field} or field
 * - operators: >, <, >=, <=, =, !=
 * - logical: and, or, not, &&, ||, !
 * - parentheses
 * - function selected(field, 'choice')
 *
 * Returns: true|false|null (null = could not evaluate)
 */
class ConstraintEvaluator
{
    protected string $input;
    protected int $pos;
    protected int $len;
    protected array $row; // current row

    public function evaluate(string $expr, array $row)
    {
        $this->input = trim($expr);
        $this->pos = 0;
        $this->len = strlen($this->input);
        $this->row = $row;

        try {
            $val = $this->parseExpression();
            // if remaining non-space chars -> cannot fully parse
            $this->skipWhitespace();
            if ($this->pos < $this->len) {
                // cannot parse fully — return null to indicate unknown
                return null;
            }
            // Normalize to boolean-ish
            return $this->toBool($val);
        } catch (\Throwable $t) {
            return null;
        }
    }

    protected function parseExpression()
    {
        return $this->parseOr();
    }

    protected function parseOr()
    {
        $left = $this->parseAnd();
        while (true) {
            $this->skipWhitespace();
            if ($this->matchWord('or') || $this->matchOperator('||')) {
                $right = $this->parseAnd();
                $left = ($this->toBool($left) || $this->toBool($right));
                continue;
            }
            break;
        }
        return $left;
    }

    protected function parseAnd()
    {
        $left = $this->parseNot();
        while (true) {
            $this->skipWhitespace();
            if ($this->matchWord('and') || $this->matchOperator('&&')) {
                $right = $this->parseNot();
                $left = ($this->toBool($left) && $this->toBool($right));
                continue;
            }
            break;
        }
        return $left;
    }

    protected function parseNot()
    {
        $this->skipWhitespace();
        if ($this->matchWord('not') || $this->matchOperator('!')) {
            $val = $this->parseNot();
            return !$this->toBool($val);
        }
        return $this->parseComparison();
    }

    protected function parseComparison()
    {
        $left = $this->parsePrimary();
        $this->skipWhitespace();
        // comparison operators
        foreach (['>=','<=','!=','>','<','='] as $op) {
            if ($this->matchOperator($op)) {
                $right = $this->parsePrimary();
                return $this->compare($left, $right, $op);
            }
        }
        return $left;
    }

    protected function parsePrimary()
    {
        $this->skipWhitespace();
        if ($this->peek() === '(') {
            $this->pos++;
            $val = $this->parseExpression();
            $this->skipWhitespace();
            if ($this->peek() === ')') { $this->pos++; }
            return $val;
        }

        // string literal '...'
        if ($this->peek() === "'" || $this->peek() === '"') {
            $quote = $this->peek();
            $this->pos++;
            $start = $this->pos;
            while ($this->pos < $this->len && $this->input[$this->pos] !== $quote) $this->pos++;
            $s = substr($this->input, $start, $this->pos - $start);
            $this->pos++; // skip closing
            return $s;
        }

        // number
        if (preg_match('/[0-9]/', $this->peek() ?? '')) {
            $num = $this->readWhile('/[0-9\.]/');
            if (strpos($num, '.') !== false) return (float)$num;
            return (int)$num;
        }

        // function selected(...) or identifier possibly ${field}
        // handle ${field} syntax
        if ($this->peek() === '$') {
            // expect ${field}
            if ($this->peekN(2) === '${') {
                $this->pos += 2;
                $name = $this->readWhile('/[A-Za-z0-9_\-\.]/');
                if ($this->peek() === '}') $this->pos++;
                return $this->getFieldValue($name);
            }
        }

        // identifier or function
        $id = $this->readWhile('/[A-Za-z0-9_\-\.]/');
        if ($id === '') {
            // unknown token
            throw new \RuntimeException("Unexpected token at pos {$this->pos}");
        }

        $this->skipWhitespace();
        if ($this->peek() === '(') {
            // function call
            $this->pos++; // skip '('
            $args = [];
            while (true) {
                $this->skipWhitespace();
                if ($this->peek() === ')') { $this->pos++; break; }
                $args[] = $this->parseExpression();
                $this->skipWhitespace();
                if ($this->peek() === ',') { $this->pos++; continue; }
            }
            return $this->callFunction($id, $args);
        }

        // plain identifier — treat as field name
        return $this->getFieldValue($id);
    }

    protected function callFunction($name, $args)
    {
        $name = strtolower($name);
        if ($name === 'selected') {
            // selected(field, 'choice') or selected(${field}, 'choice')
            if (count($args) >= 2) {
                $fieldVal = $args[0];
                $choice = $args[1];
                if ($fieldVal === null) return false;
                // ODK selected often outputs space-separated choices or CSV; support both
                if (!is_string($fieldVal)) $fieldVal = (string)$fieldVal;
                $parts = preg_split('/[\\s,]+/', trim($fieldVal));
                return in_array((string)$choice, $parts, true);
            }
            return false;
        }

        // unsupported function -> cannot evaluate
        throw new \RuntimeException("Function {$name} not supported in constraints");
    }

    protected function getFieldValue($name)
    {
        // Try direct name
        if (array_key_exists($name, $this->row)) {
            return $this->castValue($this->row[$name]);
        }
        // Try with braced ${name} variations removed or replaced underscores/dashes
        $clean = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $name);
        if (array_key_exists($clean, $this->row)) return $this->castValue($this->row[$clean]);

        // If not present, return null
        return null;
    }

    protected function castValue($v)
    {
        if ($v === null) return null;
        $v = trim((string)$v);
        if ($v === '') return '';
        if (is_numeric($v)) {
            if (strpos($v, '.') !== false) return (float)$v;
            return (int)$v;
        }
        return $v;
    }

    protected function compare($left, $right, $op)
    {
        // If both numeric, numeric compare
        if (is_numeric($left) && is_numeric($right)) {
            $ln = (float)$left; $rn = (float)$right;
            return match($op) {
                '>' => $ln > $rn,
                '<' => $ln < $rn,
                '>=' => $ln >= $rn,
                '<=' => $ln <= $rn,
                '=' => $ln == $rn,
                '!=' => $ln != $rn,
                default => false,
            };
        }

        // Else compare strings (case sensitive)
        $ls = (string)$left; $rs = (string)$right;
        return match($op) {
            '>' => $ls > $rs,
            '<' => $ls < $rs,
            '>=' => $ls >= $rs,
            '<=' => $ls <= $rs,
            '=' => $ls == $rs,
            '!=' => $ls != $rs,
            default => false,
        };
    }

    // Helpers

    protected function skipWhitespace() {
        while ($this->pos < $this->len && ctype_space($this->input[$this->pos])) $this->pos++;
    }

    protected function peek() {
        return $this->pos < $this->len ? $this->input[$this->pos] : null;
    }

    protected function peekN($n) {
        return substr($this->input, $this->pos, $n);
    }

    protected function matchOperator($op)
    {
        $l = strlen($op);
        if (substr($this->input, $this->pos, $l) === $op) {
            $this->pos += $l;
            return true;
        }
        return false;
    }

    protected function matchWord($word)
    {
        $len = strlen($word);
        $substr = strtolower(substr($this->input, $this->pos, $len));
        if ($substr === $word) {
            $next = $this->pos + $len;
            // ensure word boundary
            $after = $next >= $this->len ? ' ' : $this->input[$next];
            if (!preg_match('/[A-Za-z0-9_]/', $after)) {
                $this->pos += $len;
                return true;
            }
        }
        return false;
    }

    protected function readWhile($pattern)
    {
        $start = $this->pos;
        while ($this->pos < $this->len && preg_match($pattern, $this->input[$this->pos])) $this->pos++;
        return substr($this->input, $start, $this->pos - $start);
    }

    protected function toBool($v)
    {
        if (is_bool($v)) return $v;
        if ($v === null) return false;
        if (is_numeric($v)) return ((float)$v) != 0;
        $s = strtolower(trim((string)$v));
        if ($s === 'true' || $s === 'oui' || $s === 'yes' || $s === '1') return true;
        if ($s === 'false' || $s === 'non' || $s === 'no' || $s === '0' || $s === '') return false;
        return (bool)$s;
    }
}
