<?php

namespace App\Services;

use App\Enums\ChangeType;
use App\Enums\RightToWorkBasis;
use App\Models\Business;
use App\Models\Employee;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employee record rules.
 * - validate(): Add employee, compliance-rules.md §1. Fields depend on the right-to-work basis; those that
 *   do not apply are cleared.
 * - validatePersonal(): "Correct personal details" (never reportable).
 * - validateChange(): "Record a change", §5.
 */
class EmployeeRules
{
    /** Fields HR may correct directly. Job, pay, hours, site and right-to-work go through "Record a change". */
    public const PERSONAL_FIELDS = ['full_name', 'date_of_birth', 'nationality', 'ni_number', 'passport_number', 'passport_expiry'];

    /**
     * @throws ValidationException
     */
    public static function validate(array $input, Business $business, bool $portalInvite = false): array
    {
        $input = self::normalise($input);
        $basis = RightToWorkBasis::tryFrom((string) ($input['rtw_basis'] ?? ''));
        $limited = $basis?->timeLimited() ?? false;
        $share = $basis?->usesShareCode() ?? false;
        $sponsored = $basis?->sponsored() ?? false;
        $onlyIf = fn (bool $applies, array $rules) => $applies ? $rules : ['exclude'];

        $data = Validator::make($input, [
            ...self::personalRules(),
            'email' => self::emailRules($business, null, $portalInvite),
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],

            'rtw_basis' => ['required', Rule::enum(RightToWorkBasis::class)],
            'visa_type' => $onlyIf($basis === RightToWorkBasis::OtherVisa, ['required', Rule::in(RightToWorkBasis::OTHER_VISA_TYPES)]),
            'rtw_check_method' => ['required', Rule::in(Employee::CHECK_METHODS)],
            'rtw_check_date' => ['required', 'date', 'before_or_equal:start_date'],
            'rtw_checked_by' => ['required', 'string', 'max:255'],
            'share_code' => $onlyIf($share, ['required', 'regex:/^[A-Z0-9]{9}$/']),
            'visa_start' => $onlyIf($limited, ['required', 'date']),
            'visa_expiry' => $onlyIf($limited, ['required', 'date', 'after:start_date', 'after:visa_start']),
            'work_restrictions' => $onlyIf($limited, ['nullable', 'string', 'max:255']),

            'cos_number' => $onlyIf($sponsored, ['required', 'string', 'max:50']),
            'cos_assigned_on' => $onlyIf($sponsored, ['required', 'date']),
            'soc_code' => $onlyIf($sponsored, ['required', 'string', 'max:20']),

            'job_title' => ['required', 'string', 'max:255'],
            'salary' => [$sponsored ? 'required' : 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'start_date' => ['required', 'date'],
            'work_site_id' => ['required', Rule::exists('work_sites', 'id')->where('business_id', $business->id)->whereNull('closed_on')],
            'days_per_week' => ['required', 'numeric', 'min:0.5', 'max:7'],
            'contracted_hours' => ['required', 'numeric', 'min:1', 'max:99'],
            'contract_type' => ['required', Rule::in(Employee::CONTRACT_TYPES)],
        ], self::messages(), self::attributes())->validate();

        // Clear anything that does not apply to this basis, and set derived fields.
        return [
            ...array_fill_keys(['visa_type', 'share_code', 'visa_start', 'visa_expiry', 'work_restrictions', 'cos_number', 'cos_assigned_on', 'soc_code'], null),
            ...$data,
            'visa_type' => $basis->fixedVisaType() ?? ($data['visa_type'] ?? null),
            'follow_up_check_due' => self::followUpCheckDue($basis, $data['visa_expiry'] ?? null),
        ];
    }

    /**
     * Correct personal details. A blank NI or passport number keeps what is on file
     * (the form never receives the full value).
     *
     * @throws ValidationException
     */
    public static function validatePersonal(array $input, Employee $employee): array
    {
        $input = self::normalise(array_intersect_key($input, array_flip(self::PERSONAL_FIELDS)));
        foreach (['ni_number', 'passport_number'] as $secret) {
            $input[$secret] ??= $employee->{$secret};
        }

        return Validator::make($input, self::personalRules(), self::messages(), self::attributes())->validate();
    }

    /**
     * Record a change: returns [ChangeType, [field => new value]].
     *
     * @throws ValidationException
     */
    public static function validateChange(array $input, Employee $employee): array
    {
        $type = ChangeType::tryFrom((string) ($input['type'] ?? ''));
        if (! $type || ($type->sponsoredOnly() && ! $employee->isSponsored())) {
            throw ValidationException::withMessages(['type' => 'Choose what changed.']);
        }
        $value = self::normalise([$type->field() => $input['value'] ?? null])[$type->field()];
        $current = $employee->{$type->field()};

        $rules = match ($type) {
            ChangeType::JobTitle => ['required', 'string', 'max:255'],
            ChangeType::Soc => ['required', 'string', 'max:20'],
            ChangeType::SalaryReduction => ['required', 'numeric', 'min:0', 'max:99999999', ...($current !== null ? ['lt:'.$current] : [])],
            ChangeType::SalaryIncrease => ['required', 'numeric', 'min:0', 'max:99999999', ...($current !== null ? ['gt:'.$current] : [])],
            ChangeType::Hours => ['required', 'numeric', 'min:1', 'max:99'],
            ChangeType::Address => ['required', 'string', 'max:500'],
            ChangeType::Phone => ['required', 'string', 'max:50'],
            ChangeType::Email => self::emailRules($employee->business, $employee, false),
        };
        $messages = [
            'value.lt' => 'A reduction must be less than the current salary. For a rise, choose "Salary – increase".',
            'value.gt' => 'An increase must be more than the current salary. For a cut, choose "Salary – reduction".',
            'value.required' => 'Enter the new value.',
            ...array_combine(array_map(fn ($k) => str_replace('email.', 'value.', $k), array_keys(self::messages())), self::messages()),
        ];
        Validator::make(['value' => $value], ['value' => $rules], $messages, ['value' => 'new value'])->validate();

        return [$type, [$type->field() => $value]];
    }

    /** Follow-up check is due on the visa/permission expiry; none for permanent permission. */
    public static function followUpCheckDue(RightToWorkBasis $basis, ?string $visaExpiry): ?string
    {
        return $basis->timeLimited() ? $visaExpiry : null;
    }

    private static function personalRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'ni_number' => ['nullable', 'regex:/^[A-Z]{2}\d{6}[A-D]$/'],
            'passport_number' => ['nullable', 'regex:/^[A-Z0-9]{5,20}$/'],
            'passport_expiry' => ['nullable', 'date'],
        ];
    }

    /** Unique within the business; a portal login also needs an email no other account uses. */
    private static function emailRules(Business $business, ?Employee $employee, bool $portalInvite): array
    {
        $rules = ['required', 'email', 'max:255', Rule::unique('employees', 'email')->where('business_id', $business->id)->ignore($employee?->id)];
        if ($portalInvite || $employee?->user_id) {
            $rules[] = Rule::unique('users', 'email')->ignore($employee?->user_id);
        }

        return $rules;
    }

    private static function normalise(array $input): array
    {
        $out = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);
        foreach (['ni_number', 'passport_number', 'share_code', 'cos_number', 'soc_code'] as $field) {
            if (isset($out[$field])) {
                $out[$field] = strtoupper(preg_replace('/[\s-]+/', '', $out[$field]));
            }
        }
        if (isset($out['email'])) {
            $out['email'] = mb_strtolower($out['email']);
        }
        if (isset($out['salary'])) {
            $out['salary'] = str_replace(['£', ',', ' '], '', (string) $out['salary']);
        }

        return $out;
    }

    private static function messages(): array
    {
        return [
            'rtw_check_date.before_or_equal' => 'The right-to-work check must be done on or before the start date.',
            'visa_expiry.after' => 'The visa expires before the start date. They cannot start work.',
            'share_code.required' => 'Enter the share code. This right-to-work basis is checked online with one.',
            'share_code.regex' => 'A share code has 9 letters and numbers, like W7X 9KP 2QR.',
            'ni_number.regex' => 'Enter a National Insurance number like QQ 12 34 56 C.',
            'passport_number.regex' => 'Enter the passport number using letters and numbers only.',
            'work_site_id.required' => 'Choose a work site. Add one under Settings → Work sites first if the list is empty.',
            'work_site_id.exists' => 'Choose one of your open work sites.',
            'email.unique' => 'Someone with this email is already on SponsorSafe. Use a different email.',
            'date_of_birth.before' => 'The date of birth must be in the past.',
        ];
    }

    private static function attributes(): array
    {
        return [
            'full_name' => 'full legal name', 'rtw_basis' => 'right-to-work basis', 'rtw_check_method' => 'check method',
            'rtw_check_date' => 'check date', 'rtw_checked_by' => 'checked by', 'visa_start' => 'visa / permission start',
            'visa_expiry' => 'visa / permission expiry', 'cos_number' => 'CoS number', 'cos_assigned_on' => 'CoS assigned date',
            'soc_code' => 'SOC code', 'ni_number' => 'National Insurance number', 'work_site_id' => 'work site',
            'days_per_week' => 'working days per week', 'contracted_hours' => 'contracted hours per week',
        ];
    }
}
