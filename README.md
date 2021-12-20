# Core Excel Validator
<p>
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<!-- <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a> -->
<!-- <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a> -->
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

This package helps you to validate your excel sheet cell by cell. You do not need to store data blindly, you can rule each cell to match your expectation.

## Installation

Require this package with composer.

```shell
composer require corealg/excel-validator
```

## Usage
```php
<?php
// initilaize Core Excel Validator
$excelValidator = new ExcelValidator();

// make rules
$rules = [
    'first_name' => 'required',
    'last_name' => 'nullable',
    'email' => 'required|email|max:30',
    'joining_date' => 'date:Y-m-d'
];

// validate the sheet
$response = $excelValidator->validate($_FILES['file'], $rules);

// Success response
// [
//   "status" => "success"
//   "code" => 200
//   "errors" => []
//   "data" => [
//     1 => [
//       "first_name" => "Mizanur"
//       "last_name" => "Rahman"
//       "email" => "test@example.com"
//       "joining_date" => "2021-01-01"
//     ]
//   ]
// ]

// Validation error Response
// [
//   "status" => "error"
//   "code" => 422
//   "errors" => [
//      0 => "The First name is required at row 11"
//      1 => "The Joining date is not valid date format at row 18"
//      2 => "The Name is required at row 20"
//   ]
//   "data" => null
// ]
```

## Creating Rules
Use column's name for your rule's index (all lower case and replace the black space with the underscore `[_]`)

e.g. for column name `First Name` your rule will be
```php
<?php 
$rule = [
    'first_name' => 'required'
];
```
Check this link to get the available validation rules: https://github.com/rakit/validation#available-rules

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## Authors
- [Mizanur Rahman (mizan3008@gmail.com)](https://github.com/mizan3008)

## License
[MIT License](https://choosealicense.com/licenses/mit/)

Copyright (c) 2021 CoreAlg