<?php

namespace CoreAlg\ExcelValidator;

use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class ExcelValidator
{
    public $tmp_upload_path = null;

    private $file = [];
    private $rules = null;

    private $path = null;
    private $error = null;
    private $validation_errors = [];
    private $processed_data = [];

    private $spread_sheet_data = [];
    private $spread_sheet_header = [];

    public function __construct()
    {
        $this->path = storage_path('text-excel.xlsx');
    }

    public function validate($file, array $rules): array
    {
        $this->file = $file;
        $this->rules = $rules;

        if ($this->validateTheFileType() === false) {
            return [
                'status' => 'error',
                'message' => $this->error,
                'data' => null,
            ];
        }

        if ($this->uploadTheFile() === false) {
            return [
                'status' => 'error',
                'message' => $this->error,
                'data' => null,
            ];
        }

        if ($this->readTheFile() === false) {
            return [
                'status' => 'error',
                'message' => $this->error,
                'data' => null,
            ];
        }

        $this->validateOriginalData();

        return [
            'status' => 'success',
            'message' => count($this->validation_errors) > 0 ? 'Validation errors' : '',
            'validation_errors' => $this->validation_errors,
            'data' => $this->processed_data,
        ];
    }

    private function validateTheFileType(): bool
    {
        $allowed_file_type = [
            'application/vnd.ms-excel',
            'text/xls',
            'text/xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        if (!in_array($this->file["type"], $allowed_file_type)) {
            $this->error = "Invalid File Type. Upload Excel File.";
            return false;
        }

        return true;
    }

    private function uploadTheFile(): bool
    {
        try {
            $temp = explode(".", $this->file['name']);
            $new_file_name = 'temp-excel-file-' . round(microtime(true)) . '.' . end($temp);

            if (is_null($this->tmp_upload_path)) {
                $target_path = storage_path("{$new_file_name}");
            } else {
                $target_path = "{$this->tmp_upload_path}/{$new_file_name}";
            }

            move_uploaded_file($this->file["tmp_name"], $target_path);
            $this->path = $target_path;
            return true;
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }

    private function readTheFile(): bool
    {
        try {
            $reader = new XlsxReader();

            $spread_sheet = $reader->load($this->path);
            $excel_sheet = $spread_sheet->getActiveSheet();

            $this->spread_sheet_data = $excel_sheet->toArray();

            $this->spread_sheet_header = $this->spread_sheet_data[0];

            // unset the header row from the original data array
            unset($this->spread_sheet_data[0]);

            foreach ($this->spread_sheet_header as $key => $hc_value) {
                $this->spread_sheet_header[$key] = $this->replaceAnySpecialCharactersFromString(strtolower($hc_value));
            }
            return true;
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return false;
        }
    }

    private function replaceAnySpecialCharactersFromString(string $string, string $replacement = '_'): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', $replacement, $string);
    }

    private function validateOriginalData(): void
    {
        foreach ($this->spread_sheet_data as $key => $row) {
            $row_data = [];
            foreach ($this->rules as $rule_key => $rule) {

                // get the original data index from the spread_sheet_header array based on the  rule_key
                $spread_sheet_data_index = array_search($rule_key, $this->spread_sheet_header);

                $spread_sheet_data_value = $row[$spread_sheet_data_index] ?? "";

                if ($rule === 'required') {
                    if (strlen($spread_sheet_data_value) < 1) {
                        $new_key = $key + 1;
                        $this->validation_errors[] = "{$rule_key} is missing at row {$new_key}";
                    }
                }

                $row_data[$rule_key] = $spread_sheet_data_value;
            }

            $this->processed_data[] = $row_data;
        }
    }

    public function __destruct()
    {
        if (!is_null($this->path)) {
            unlink($this->path);
        }

        $this->file = [];
        $this->rules = null;

        $this->path = null;
        $this->error = null;
        $this->validation_errors = [];
        $this->processed_data = [];

        $this->spread_sheet_data = [];
        $this->spread_sheet_header = [];
    }
}
