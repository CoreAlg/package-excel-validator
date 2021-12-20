<?php

namespace CoreAlg\ExcelValidator;

use Exception;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Rakit\Validation\Validator as RakitValidator;

class Validator
{
    public $temp_upload_path = null;

    private $file = [];
    private $rules = null;

    private $path = null;
    private $error = null;
    private $code = null;

    private $spread_sheet_data = [];

    private $rakitValidator = null;

    public function __construct()
    {
        $this->rakitValidator = new RakitValidator();
        $this->temp_upload_path = sys_get_temp_dir();
    }

    public function validate($file, array $rules): array
    {
        $this->file = $file;
        $this->rules = $rules;

        if ($this->validateTheFileType() === false) {
            return $this->error_response();
        }

        if ($this->uploadTheFile() === false) {
            return $this->error_response();
        }

        if ($this->readTheFile() === false) {
            return $this->error_response();
        }

        if ($this->validateSheet() === false) {
            return $this->error_response();
        }

        $data = $this->getValidatedData();

        return [
            'status' => 'success',
            'code' => 200,
            'errors' => [],
            'data' => $data,
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
            $this->code = 422;
            $this->error = ["Invalid File Type. Upload Excel File."];
            return false;
        }

        return true;
    }

    private function uploadTheFile(): bool
    {
        try {
            $temp_name = explode(".", $this->file['name']);
            $new_file_name = 'temp-excel-file-' . round(microtime(true)) . '.' . end($temp_name);

            $target_path = "{$this->temp_upload_path}/{$new_file_name}";

            move_uploaded_file($this->file["tmp_name"], $target_path);

            // store the actual readable path, so we can use it during read the file
            $this->path = $target_path;
            return true;
        } catch (Exception $ex) {
            $this->error = [$ex->getMessage()];
            $this->code = 500;
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
            $this->error = [$ex->getMessage()];
            $this->code = 500;
            return false;
        }
    }

    private function replaceAnySpecialCharactersFromString(string $string, string $replacement = '_'): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', $replacement, $string);
    }

    private function validateSheet()
    {
        $validate = true;

        foreach ($this->spread_sheet_data as $index => $row) {

            $actual_row_number = $index + 1;

            $validation = $this->rakitValidator->validate($row, $this->rules);

            if ($validation->fails()) {

                $validate = false;

                $this->code = 422;

                foreach ($validation->errors()->all() as $error) {
                    $this->error[] = "{$error} at row {$actual_row_number}";
                }
            }
        }

        return $validate;
    }

    private function getValidatedData(): array
    {
        $my_data = [];

        foreach ($this->spread_sheet_data as $index => $row) {

            $validation = $this->rakitValidator->validate($row, $this->rules);

            $valid_data = $validation->getValidData();

            $invalid_data = (array) $validation->getInvalidData();

            if (count($invalid_data) > 0) {
                foreach ($invalid_data as $key => $value) {
                    $valid_data[$key] = null;
                }
            }

            $my_data[$index] = $valid_data;
        }

        return $my_data;
    }

    private function error_response(): array
    {
        return [
            'status' => 'error',
            'code' => $this->code,
            'errors' => $this->error,
            'data' => null,
        ];
    }

    public function __destruct()
    {
        if (!is_null($this->path)) {
            unlink($this->path);
        }

        $$this->temp_upload_path = null;

        $$this->file = [];
        $$this->rules = null;

        $$this->path = null;
        $$this->error = null;
        $$this->code = null;

        $$this->spread_sheet_data = [];

        $$this->rakitValidator = null;
    }
}
