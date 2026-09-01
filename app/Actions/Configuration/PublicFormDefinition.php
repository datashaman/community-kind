<?php

namespace App\Actions\Configuration;

final class PublicFormDefinition
{
    /**
     * @return array<string, array{label: string, description: string, fields: list<array{key: string, label: string, type: string, fixed_required: bool}>}>
     */
    public static function catalogue(): array
    {
        return [
            'event_registration' => [
                'label' => 'Event registration',
                'description' => 'Collect the identity needed to reserve a place at a community event.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'fixed_required' => true],
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'fixed_required' => true],
                ],
            ],
            'volunteer_registration' => [
                'label' => 'Volunteer application',
                'description' => 'Collect volunteer interests and availability for an opportunity.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'fixed_required' => true],
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'fixed_required' => true],
                    ['key' => 'interests', 'label' => 'Interests', 'type' => 'multiselect', 'fixed_required' => false],
                    ['key' => 'availability', 'label' => 'Availability', 'type' => 'multiselect', 'fixed_required' => true],
                ],
            ],
            'in_kind_offer' => [
                'label' => 'In-kind offer',
                'description' => 'Collect the details needed to assess a donated item or service.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'fixed_required' => true],
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'fixed_required' => true],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'text', 'fixed_required' => true],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'fixed_required' => true],
                    ['key' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'fixed_required' => true],
                    ['key' => 'unit', 'label' => 'Unit', 'type' => 'text', 'fixed_required' => true],
                    ['key' => 'estimated_value_minor', 'label' => 'Estimated value', 'type' => 'money', 'fixed_required' => false],
                    ['key' => 'currency', 'label' => 'Currency', 'type' => 'currency', 'fixed_required' => false],
                    ['key' => 'condition', 'label' => 'Condition', 'type' => 'text', 'fixed_required' => true],
                ],
            ],
            'supporter_profile' => [
                'label' => 'Supporter profile',
                'description' => 'Collect the supporter contact details used by the self-service portal.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'fixed_required' => true],
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'fixed_required' => true],
                    ['key' => 'telephone', 'label' => 'Telephone number', 'type' => 'tel', 'fixed_required' => false],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function purposes(): array
    {
        return array_keys(self::catalogue());
    }

    /** @return list<array{key: string, label: string, type: string, fixed_required: bool}> */
    public static function fields(string $purpose): array
    {
        return self::catalogue()[$purpose]['fields'] ?? [];
    }

    /** @return list<string> */
    public static function fieldKeys(string $purpose): array
    {
        return array_column(self::fields($purpose), 'key');
    }

    /** @return list<string> */
    public static function fixedRequiredKeys(string $purpose): array
    {
        return array_column(array_filter(
            self::fields($purpose),
            fn (array $field): bool => $field['fixed_required'],
        ), 'key');
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array{key: string, label: string, type: string, required: bool, fixedRequired: bool}>
     */
    public static function displayFields(string $purpose, array $definition): array
    {
        $catalogue = [];
        foreach (self::fields($purpose) as $field) {
            $catalogue[$field['key']] = $field;
        }
        $required = array_values(array_filter(
            is_array($definition['required_fields'] ?? null) ? $definition['required_fields'] : [],
            is_string(...),
        ));
        $orderedKeys = [];
        $storedFields = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        foreach ($storedFields as $storedField) {
            if (is_array($storedField) && is_string($storedField['key'] ?? null) && isset($catalogue[$storedField['key']])) {
                $orderedKeys[] = $storedField['key'];
            }
        }
        if ($orderedKeys === []) {
            $orderedKeys = array_keys($catalogue);
        }

        $displayFields = [];
        foreach ($orderedKeys as $key) {
            $field = $catalogue[$key];
            $displayFields[] = [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => $field['fixed_required'] || in_array($key, $required, true),
                'fixedRequired' => $field['fixed_required'],
            ];
        }

        return $displayFields;
    }

    /**
     * @param  list<string>  $orderedKeys
     * @param  list<string>  $requiredKeys
     * @return array{form: string, required_fields: list<string>, fields: list<array{key: string, type: string, required: bool}>}
     */
    public static function build(string $purpose, array $orderedKeys, array $requiredKeys): array
    {
        $catalogue = [];
        foreach (self::fields($purpose) as $field) {
            $catalogue[$field['key']] = $field;
        }
        $required = array_values(array_unique([...self::fixedRequiredKeys($purpose), ...$requiredKeys]));
        $fields = [];
        $orderedRequired = [];
        foreach ($orderedKeys as $key) {
            $isRequired = in_array($key, $required, true);
            if ($isRequired) {
                $orderedRequired[] = $key;
            }
            $fields[] = ['key' => $key, 'type' => $catalogue[$key]['type'], 'required' => $isRequired];
        }

        return [
            'form' => $purpose,
            'required_fields' => $orderedRequired,
            'fields' => $fields,
        ];
    }
}
