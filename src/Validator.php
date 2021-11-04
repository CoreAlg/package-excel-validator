<?php

namespace CoreAlg\ExcelValidator;

use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Rakit\Validation\Validator as RakitValidator;

class Validator
{
    public $tmp_upload_path = null;

    private $file = [];
    private $rules = null;

    private $path = null;
    private $error = null;
    private $validation_errors = [];
    private $validated_data = [];

    private $spread_sheet_data = [];

    private $rakitValidator;

    public function __construct()
    {
        $this->rakitValidator = new RakitValidator();
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

        if (count($this->validation_errors) > 0) {
            return [
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $this->validation_errors,
                'data' => $this->validated_data,
            ];
        } else {
            return [
                'status' => 'success',
                'message' => '',
                'data' => $this->validated_data,
            ];
        }
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

            $spread_sheet_header = $this->spread_sheet_data[0];

            foreach ($spread_sheet_header as $key => $hc_value) {
                $spread_sheet_header[$key] = $this->replaceAnySpecialCharactersFromString(strtolower($hc_value));
            }

            // unset the header row from the original data array
            unset($this->spread_sheet_data[0]);

            // change the numeric index to a proper readable name
            foreach ($this->spread_sheet_data as $key => $row) {
                $this->spread_sheet_data[$key] = array_combine($spread_sheet_header, $row);
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
        foreach ($this->spread_sheet_data as $index => $row) {

            $actual_row_number = $index + 1;

            $validation = $this->rakitValidator->validate($row, $this->rules);

            if ($validation->fails()) {
                foreach ($validation->errors()->all() as $error) {
                    $this->validation_errors[] = "{$error} at row {$actual_row_number}";
                }
            }

            $validated_data = $validation->getValidData();

            $invalid_data = $validation->getInvalidData();

            if (count($invalid_data) > 0) {
                foreach ($invalid_data as $key => $value) {
                    $validated_data[$key] = null;
                }
            }

            $this->validated_data[$index] = $validated_data;
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
        $this->validated_data = [];

        $this->spread_sheet_data = [];

        $this->rakitValidator = null;
    }
}
