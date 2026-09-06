<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\MembershipGrade;
use App\Security\Security;
use App\Validation\Validator;
use DateTimeImmutable;
use DomainException;
use Throwable;

final class MembershipService
{
    private const DOCUMENT_TYPES = ['qualification', 'professional_certificate', 'identification', 'other'];

    public function __construct(
        private readonly MembershipRepositoryInterface $repository,
        private readonly DocumentStorageInterface $documents,
        private readonly Validator $validator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function grades(): array
    {
        return array_map(
            static fn (array $grade): array => (new MembershipGrade($grade))->publicDetails(),
            $this->repository->activeGrades(),
        );
    }

    /** @return array<string, mixed>|null */
    public function currentApplication(int $userId): ?array
    {
        return $userId > 0 ? $this->repository->currentApplication($userId) : null;
    }

    /** @param array<string, mixed> $input */
    public function saveDraft(int $userId, array $input): MembershipResult
    {
        if ($userId < 1) {
            return MembershipResult::failure('unauthorized', 'Authentication is required.');
        }

        $rules = [
            'application_public_id' => 'nullable|string|max:36',
            'membership_grade' => 'nullable|string|max:36',
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|string|max:10',
            'phone' => 'nullable|string',
            'contact_email' => 'nullable|email|max:254',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'professional_area' => 'nullable|string|max:160',
            'current_role' => 'nullable|string|max:160',
            'years_experience' => 'nullable|integer|min:0|max:80',
            'education_summary' => 'nullable|string|max:5000',
            'employment_summary' => 'nullable|string|max:5000',
        ];
        if (!$this->validator->validate($input, $rules)) {
            return MembershipResult::failure('validation_failed', 'Review the highlighted application fields.', $this->validator->errors());
        }

        $values = $this->clean($this->validator->validated());
        if (is_string($values['phone'] ?? null) && mb_strlen($values['phone']) > 40) {
            return MembershipResult::failure('validation_failed', 'Enter a valid phone number.', [
                'phone' => ['The phone number must not exceed 40 characters.'],
            ]);
        }
        if (isset($values['date_of_birth']) && !$this->validDate((string) $values['date_of_birth'])) {
            return MembershipResult::failure('validation_failed', 'Enter a valid date of birth.', [
                'date_of_birth' => ['Enter a valid date in YYYY-MM-DD format.'],
            ]);
        }
        $applicationId = $this->uuidOrNull($values['application_public_id'] ?? null);
        if (($values['application_public_id'] ?? null) !== null && $applicationId === null) {
            return MembershipResult::failure('validation_failed', 'The application identifier is invalid.');
        }
        $gradeId = $this->uuidOrNull($values['membership_grade'] ?? null);
        if (($values['membership_grade'] ?? null) !== null && $gradeId === null) {
            return MembershipResult::failure('validation_failed', 'Select an available membership grade.', [
                'membership_grade' => ['Select an available membership grade.'],
            ]);
        }

        try {
            $application = $this->repository->saveDraft(
                $userId,
                $applicationId,
                $gradeId,
                $this->sections($values),
                new DateTimeImmutable('now'),
            );

            return MembershipResult::success('draft_saved', 'Your application draft has been saved.', $application);
        } catch (DomainException $exception) {
            return MembershipResult::failure('application_unavailable', $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $file */
    public function uploadDocument(int $userId, string $applicationPublicId, string $documentType, array $file): MembershipResult
    {
        if ($userId < 1 || !$this->validUuid($applicationPublicId)) {
            return MembershipResult::failure('application_unavailable', 'This application cannot accept documents.');
        }
        if (!in_array($documentType, self::DOCUMENT_TYPES, true)) {
            return MembershipResult::failure('validation_failed', 'Select a valid document type.', [
                'document_type' => ['Select a valid document type.'],
            ]);
        }

        $application = $this->repository->applicationForUser($userId, $applicationPublicId);
        if ($application === null || !in_array($application['status'] ?? null, ['draft', 'query_raised'], true)) {
            return MembershipResult::failure('application_unavailable', 'This application cannot accept documents.');
        }

        $stored = null;
        try {
            $stored = $this->documents->store($file);
            $this->repository->addDocument($userId, $applicationPublicId, $stored + ['document_type' => $documentType], new DateTimeImmutable('now'));

            return MembershipResult::success('document_uploaded', 'The supporting document was uploaded securely.');
        } catch (Throwable $exception) {
            if (is_array($stored) && is_string($stored['storage_path'] ?? null)) {
                $this->documents->delete($stored['storage_path']);
            }
            $message = $exception instanceof DomainException
                ? $exception->getMessage()
                : (str_starts_with($exception->getMessage(), 'The document') || str_starts_with($exception->getMessage(), 'Only PDF') || str_starts_with($exception->getMessage(), 'Select a document')
                    ? $exception->getMessage()
                    : 'The document could not be uploaded.');

            return MembershipResult::failure('document_failed', $message, ['document' => [$message]]);
        }
    }

    /** @param array<string, mixed> $input */
    public function submit(int $userId, array $input): MembershipResult
    {
        $applicationPublicId = is_string($input['application_public_id'] ?? null) ? trim($input['application_public_id']) : '';
        $declarationName = is_string($input['declaration_name'] ?? null) ? trim($input['declaration_name']) : '';
        $accepted = filter_var($input['declaration_accepted'] ?? false, FILTER_VALIDATE_BOOL);
        if ($userId < 1 || !$this->validUuid($applicationPublicId)) {
            return MembershipResult::failure('application_unavailable', 'This application cannot be submitted.');
        }

        $application = $this->repository->applicationForUser($userId, $applicationPublicId);
        if ($application === null || !in_array($application['status'] ?? null, ['draft', 'query_raised'], true)) {
            return MembershipResult::failure('application_unavailable', 'This application cannot be submitted.');
        }

        $errors = $this->submissionErrors($application, $declarationName, $accepted);
        if ($errors !== []) {
            return MembershipResult::failure('validation_failed', 'Complete every required section before submitting.', $errors);
        }

        try {
            $reference = 'AIMS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(8)));
            $submitted = $this->repository->submit(
                $userId,
                $applicationPublicId,
                $reference,
                $declarationName,
                new DateTimeImmutable('now'),
            );
            if ($submitted === null) {
                return MembershipResult::failure('application_unavailable', 'This application cannot be submitted.');
            }

            return MembershipResult::success(
                'submitted',
                'Your application has been submitted for review. Submission does not activate membership.',
                $submitted,
            );
        } catch (DomainException $exception) {
            return MembershipResult::failure('validation_failed', $exception->getMessage(), ['documents' => [$exception->getMessage()]]);
        }
    }

    public function cancel(int $userId, string $applicationPublicId): MembershipResult
    {
        if ($userId < 1 || !$this->validUuid($applicationPublicId)) {
            return MembershipResult::failure('application_unavailable', 'This application cannot be cancelled.');
        }
        $cancelled = $this->repository->cancel($userId, $applicationPublicId, new DateTimeImmutable('now'));

        return $cancelled === null
            ? MembershipResult::failure('application_unavailable', 'This application cannot be cancelled.')
            : MembershipResult::success('cancelled', 'Your application has been cancelled.', $cancelled);
    }

    /** @param array<string, mixed> $values
     *  @return array<string, array<string, mixed>>
     */
    private function sections(array $values): array
    {
        return [
            'personal_information' => $this->only($values, ['first_name', 'middle_name', 'last_name', 'date_of_birth']),
            'contact_information' => $this->only($values, ['phone', 'contact_email', 'address', 'city', 'state', 'country']),
            'professional_details' => $this->only($values, ['professional_area', 'current_role', 'years_experience']),
            'education' => ['summary' => $values['education_summary'] ?? null],
            'employment' => ['summary' => $values['employment_summary'] ?? null],
        ];
    }

    /** @param array<string, mixed> $application
     *  @return array<string, list<string>>
     */
    private function submissionErrors(array $application, string $declarationName, bool $accepted): array
    {
        $requirements = [
            'membership_grade' => [$application['grade_public_id'] ?? null, 'Select a membership grade.'],
            'first_name' => [$application['personal_information']['first_name'] ?? null, 'Enter your first name.'],
            'last_name' => [$application['personal_information']['last_name'] ?? null, 'Enter your last name.'],
            'phone' => [$application['contact_information']['phone'] ?? null, 'Enter your phone number.'],
            'contact_email' => [$application['contact_information']['contact_email'] ?? null, 'Enter your contact email address.'],
            'address' => [$application['contact_information']['address'] ?? null, 'Enter your contact address.'],
            'country' => [$application['contact_information']['country'] ?? null, 'Enter your country.'],
            'professional_area' => [$application['professional_details']['professional_area'] ?? null, 'Enter your professional area.'],
            'current_role' => [$application['professional_details']['current_role'] ?? null, 'Enter your current role.'],
            'education_summary' => [$application['education']['summary'] ?? null, 'Provide your education details.'],
            'employment_summary' => [$application['employment']['summary'] ?? null, 'Provide your employment history.'],
            'documents' => [$application['documents'] ?? [], 'Upload at least one supporting document.'],
            'declaration_name' => [$declarationName, 'Enter your full name for the declaration.'],
            'declaration_accepted' => [$accepted, 'Accept the declaration before submitting.'],
        ];
        $errors = [];
        foreach ($requirements as $field => [$value, $message]) {
            if ($value === null || $value === '' || $value === [] || $value === false) {
                $errors[$field][] = $message;
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $values[$key] = $value === '' ? null : $value;
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $values
     *  @param list<string> $keys
     *  @return array<string, mixed>
     */
    private function only(array $values, array $keys): array
    {
        $selected = [];
        foreach ($keys as $key) {
            $selected[$key] = $values[$key] ?? null;
        }

        return $selected;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value && $date <= new DateTimeImmutable('today');
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && $this->validUuid($value) ? $value : null;
    }

    private function validUuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value) === 1;
    }
}
