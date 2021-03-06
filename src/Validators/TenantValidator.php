<?php namespace LaraLeague\MultiTenant\Validators;

use HynMe\Framework\Validators\AbstractValidator;

class TenantValidator extends AbstractValidator
{
    protected $rules = [
        'name' => ['required', 'min:3'],
    ];
}